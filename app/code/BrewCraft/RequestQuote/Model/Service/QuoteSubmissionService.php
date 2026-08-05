<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model\Service;

use BrewCraft\RequestQuote\Api\QuoteRequestItemRepositoryInterface;
use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\QuoteRequest;
use BrewCraft\RequestQuote\Model\QuoteRequestFactory;
use BrewCraft\RequestQuote\Model\QuoteRequestItem;
use BrewCraft\RequestQuote\Model\QuoteRequestItemFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as CartItem;
use Psr\Log\LoggerInterface;

class QuoteSubmissionService
{
    private const MAX_QUOTE_NAME_LENGTH = 255;
    private const MAX_CUSTOMER_MESSAGE_LENGTH = 5000;

    public function __construct(
        private readonly BusinessCustomerEligibilityService $eligibilityService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly QuoteRequestFactory $quoteRequestFactory,
        private readonly QuoteRequestItemFactory $quoteRequestItemFactory,
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly QuoteRequestItemRepositoryInterface $itemRepository,
        private readonly ResourceConnection $resourceConnection,
        private readonly Random $random,
        private readonly Json $jsonSerializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Copy the customer's active shopping cart into a quote request.
     *
     * @throws LocalizedException
     */
    public function submit(
        int $customerId,
        string $quoteName,
        ?string $customerMessage = null
    ): QuoteRequest {
        $quoteName = trim($quoteName);
        $customerMessage = trim((string)$customerMessage);

        $this->validateInput(
            $quoteName,
            $customerMessage
        );

        $businessAccount = $this->eligibilityService->validate(
            $customerId
        );

        try {
            $cart = $this->cartRepository->getActiveForCustomer(
                $customerId
            );
        } catch (\Throwable $exception) {
            throw new LocalizedException(
                __('You do not have an active shopping cart.')
            );
        }

        $visibleItems = $cart->getAllVisibleItems();

        if ($visibleItems === []) {
            throw new LocalizedException(
                __('Add at least one product to your cart before requesting a quote.')
            );
        }

        $connection = $this
            ->resourceConnection
            ->getConnection();

        $connection->beginTransaction();

        try {
            $quoteRequest = $this->createQuoteRequest(
                $cart,
                $customerId,
                (int)$businessAccount->getId(),
                $quoteName,
                $customerMessage
            );

            foreach ($visibleItems as $cartItem) {
                $this->createQuoteRequestItem(
                    $quoteRequest,
                    $cartItem
                );
            }

            $connection->commit();

            $this->logger->info(
                'BrewCraft quote request submitted.',
                [
                    'quote_request_id' => (int)$quoteRequest->getId(),
                    'quote_number' => $quoteRequest->getQuoteNumber(),
                    'customer_id' => $customerId,
                    'item_count' => count($visibleItems)
                ]
            );

            return $quoteRequest;
        } catch (LocalizedException $exception) {
            $connection->rollBack();

            throw $exception;
        } catch (\Throwable $exception) {
            $connection->rollBack();

            $this->logger->error(
                'BrewCraft quote request submission failed.',
                [
                    'customer_id' => $customerId,
                    'exception' => $exception->getMessage()
                ]
            );

            throw new LocalizedException(
                __(
                    'The quote request could not be submitted. Please try again.'
                )
            );
        }
    }

    private function validateInput(
        string $quoteName,
        string $customerMessage
    ): void {
        if ($quoteName === '') {
            throw new LocalizedException(
                __('Enter a name for your quote request.')
            );
        }

        if (mb_strlen($quoteName) > self::MAX_QUOTE_NAME_LENGTH) {
            throw new LocalizedException(
                __(
                    'The quote name cannot exceed %1 characters.',
                    self::MAX_QUOTE_NAME_LENGTH
                )
            );
        }

        if (
            mb_strlen($customerMessage)
            > self::MAX_CUSTOMER_MESSAGE_LENGTH
        ) {
            throw new LocalizedException(
                __(
                    'The quote message cannot exceed %1 characters.',
                    self::MAX_CUSTOMER_MESSAGE_LENGTH
                )
            );
        }
    }

    private function createQuoteRequest(
        Quote $cart,
        int $customerId,
        int $businessAccountId,
        string $quoteName,
        string $customerMessage
    ): QuoteRequest {
        $quoteRequest = $this->quoteRequestFactory->create();

        $quoteRequest->setData(
            [
                'customer_id' => $customerId,
                'business_account_id' => $businessAccountId,
                'quote_number' => $this->generateQuoteNumber(),
                'quote_name' => $quoteName,
                'customer_message' => $customerMessage !== ''
                    ? $customerMessage
                    : null,
                'status' => QuoteRequest::STATUS_PENDING,
                'original_subtotal' => $this->calculateSubtotal($cart),
                'proposed_subtotal' => null,
                'currency_code' => (string)$cart->getQuoteCurrencyCode(),
                'expires_at' => null
            ]
        );

        return $this->quoteRequestRepository->save(
            $quoteRequest
        );
    }

    private function createQuoteRequestItem(
        QuoteRequest $quoteRequest,
        CartItem $cartItem
    ): QuoteRequestItem {
        $quantity = (float)$cartItem->getQty();
        $unitPrice = (float)$cartItem->getCalculationPrice();
        $rowTotal = $unitPrice * $quantity;

        $requestItem = $this->quoteRequestItemFactory->create();

        $requestItem->setData(
            [
                'quote_request_id' => (int)$quoteRequest->getId(),
                'product_id' => $cartItem->getProductId()
                    ? (int)$cartItem->getProductId()
                    : null,
                'sku' => (string)$cartItem->getSku(),
                'product_name' => (string)$cartItem->getName(),
                'requested_qty' => $quantity,
                'original_price' => $unitPrice,
                'proposed_price' => null,
                'original_row_total' => $rowTotal,
                'proposed_row_total' => null,
                'product_options' => $this->serializeProductOptions(
                    $cartItem
                )
            ]
        );

        return $this->itemRepository->save(
            $requestItem
        );
    }

    private function calculateSubtotal(Quote $cart): float
    {
        $subtotal = 0.0;

        foreach ($cart->getAllVisibleItems() as $item) {
            $subtotal +=
                (float)$item->getCalculationPrice()
                * (float)$item->getQty();
        }

        return round($subtotal, 4);
    }

    private function generateQuoteNumber(): string
    {
        return sprintf(
            'BCQ-%s-%s',
            gmdate('Ymd'),
            $this->random->getRandomString(
                8,
                Random::CHARS_DIGITS
            )
        );
    }

    private function serializeProductOptions(
        CartItem $cartItem
    ): ?string {
        $options = [];

        foreach (
            [
                'info_buyRequest',
                'options',
                'additional_options'
            ] as $optionCode
        ) {
            $option = $cartItem->getOptionByCode(
                $optionCode
            );

            if ($option && $option->getValue() !== null) {
                $options[$optionCode] = $option->getValue();
            }
        }

        if ($options === []) {
            return null;
        }

        try {
            return $this->jsonSerializer->serialize(
                $options
            );
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
