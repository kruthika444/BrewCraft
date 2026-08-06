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
    ) {}

    /**
     * Copy the customer's active shopping cart into a quote request.
     *
     * Requested quantities may differ from the quantities in the cart.
     * The customer may optionally provide an expected unit price.
     *
     * @throws LocalizedException
     */
    public function submit(
        int $customerId,
        string $quoteName,
        string $customerMessage,
        array $itemRequests = []
    ): QuoteRequest {
        $quoteName = trim($quoteName);
        $customerMessage = trim($customerMessage);

        $this->validateInput(
            $quoteName,
            $customerMessage
        );

        if ($itemRequests === []) {
            throw new LocalizedException(
                __(
                    'Quote item information is missing. Please review the products and try again.'
                )
            );
        }

        $businessAccount = $this
            ->eligibilityService
            ->validate(
                $customerId
            );

        try {
            $cart = $this
                ->cartRepository
                ->getActiveForCustomer(
                    $customerId
                );
        } catch (\Throwable) {
            throw new LocalizedException(
                __('You do not have an active shopping cart.')
            );
        }

        $visibleItems = $cart->getAllVisibleItems();

        if ($visibleItems === []) {
            throw new LocalizedException(
                __(
                    'Add at least one product to your cart before requesting a quote.'
                )
            );
        }

        /*
         * Validate and prepare every item before opening the transaction.
         *
         * Only items loaded from the customer's active cart are processed.
         * Submitted item IDs that are not present in the active cart are ignored.
         */
        $preparedItems = [];
        $originalSubtotal = 0.0;
        $customerExpectedSubtotal = 0.0;
        $hasExpectedPrice = false;

        foreach ($visibleItems as $cartItem) {
            $preparedItem = $this->prepareQuoteItemData(
                $cartItem,
                $itemRequests
            );

            $preparedItems[] = [
                'cart_item' => $cartItem,
                'data' => $preparedItem
            ];

            $originalSubtotal +=
                $preparedItem['original_row_total'];

            if (
                $preparedItem['expected_row_total']
                !== null
            ) {
                $customerExpectedSubtotal +=
                    $preparedItem['expected_row_total'];

                $hasExpectedPrice = true;
            }
        }

        $originalSubtotal = round(
            $originalSubtotal,
            4
        );

        $customerExpectedSubtotal = $hasExpectedPrice
            ? round($customerExpectedSubtotal, 4)
            : null;

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
                $customerMessage,
                $originalSubtotal,
                $customerExpectedSubtotal
            );

            foreach ($preparedItems as $preparedItem) {
                /** @var CartItem $cartItem */
                $cartItem = $preparedItem['cart_item'];

                /** @var array<string, mixed> $itemData */
                $itemData = $preparedItem['data'];

                $this->createQuoteRequestItem(
                    $quoteRequest,
                    $cartItem,
                    $itemData
                );
            }

            $connection->commit();

            $this->logger->info(
                'BrewCraft quote request submitted.',
                [
                    'quote_request_id' =>
                    (int)$quoteRequest->getId(),
                    'quote_number' =>
                    $quoteRequest->getQuoteNumber(),
                    'customer_id' => $customerId,
                    'item_count' => count($preparedItems),
                    'original_subtotal' =>
                    $originalSubtotal,
                    'customer_expected_subtotal' =>
                    $customerExpectedSubtotal
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

        if (
            mb_strlen($quoteName)
            > self::MAX_QUOTE_NAME_LENGTH
        ) {
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

    /**
     * Validate submitted values for one real active-cart item.
     *
     * @return array{
     *     requested_qty: float,
     *     original_price: float,
     *     expected_price: float|null,
     *     original_row_total: float,
     *     expected_row_total: float|null
     * }
     *
     * @throws LocalizedException
     */
    private function prepareQuoteItemData(
        CartItem $cartItem,
        array $itemRequests
    ): array {
        $cartItemId = (int)$cartItem->getId();

        if (
            !isset($itemRequests[$cartItemId])
            || !is_array($itemRequests[$cartItemId])
        ) {
            throw new LocalizedException(
                __(
                    'Quote information is missing for %1.',
                    $cartItem->getName()
                )
            );
        }

        $itemInput = $itemRequests[$cartItemId];

        $requestedQty = $this->validateRequestedQuantity(
            $itemInput['requested_qty'] ?? null,
            (string)$cartItem->getName()
        );

        $expectedPrice = $this->validateExpectedPrice(
            $itemInput['expected_price'] ?? null,
            (string)$cartItem->getName()
        );

        $originalPrice = round(
            (float)$cartItem->getCalculationPrice(),
            4
        );

        if ($originalPrice < 0) {
            throw new LocalizedException(
                __(
                    'The current price for %1 is invalid.',
                    $cartItem->getName()
                )
            );
        }

        $originalRowTotal = round(
            $originalPrice * $requestedQty,
            4
        );

        $expectedRowTotal = $expectedPrice !== null
            ? round(
                $expectedPrice * $requestedQty,
                4
            )
            : null;

        return [
            'requested_qty' => $requestedQty,
            'original_price' => $originalPrice,
            'expected_price' => $expectedPrice,
            'original_row_total' => $originalRowTotal,
            'expected_row_total' => $expectedRowTotal
        ];
    }

    /**
     * @throws LocalizedException
     */
    private function validateRequestedQuantity(
        mixed $rawValue,
        string $productName
    ): float {
        $rawValue = trim(
            (string)$rawValue
        );

        if (
            $rawValue === ''
            || !is_numeric($rawValue)
        ) {
            throw new LocalizedException(
                __(
                    'Enter a valid requested quantity for %1.',
                    $productName
                )
            );
        }

        $requestedQty = round(
            (float)$rawValue,
            4
        );

        if ($requestedQty <= 0) {
            throw new LocalizedException(
                __(
                    'The requested quantity for %1 must be greater than zero.',
                    $productName
                )
            );
        }

        return $requestedQty;
    }

    /**
     * @throws LocalizedException
     */
    private function validateExpectedPrice(
        mixed $rawValue,
        string $productName
    ): ?float {
        $rawValue = trim(
            (string)$rawValue
        );

        if ($rawValue === '') {
            return null;
        }

        if (!is_numeric($rawValue)) {
            throw new LocalizedException(
                __(
                    'Enter a valid expected unit price for %1.',
                    $productName
                )
            );
        }

        $expectedPrice = round(
            (float)$rawValue,
            4
        );

        if ($expectedPrice <= 0) {
            throw new LocalizedException(
                __(
                    'The expected unit price for %1 must be greater than zero.',
                    $productName
                )
            );
        }

        return $expectedPrice;
    }

    private function createQuoteRequest(
        Quote $cart,
        int $customerId,
        int $businessAccountId,
        string $quoteName,
        string $customerMessage,
        float $originalSubtotal,
        ?float $customerExpectedSubtotal
    ): QuoteRequest {
        $quoteRequest = $this
            ->quoteRequestFactory
            ->create();

        $quoteRequest->setData(
            [
                'customer_id' => $customerId,
                'business_account_id' =>
                $businessAccountId,
                'quote_number' =>
                $this->generateQuoteNumber(),
                'quote_name' => $quoteName,
                'customer_message' =>
                $customerMessage !== ''
                    ? $customerMessage
                    : null,
                'admin_comment' => null,
                'status' =>
                QuoteRequest::STATUS_PENDING,
                'original_subtotal' =>
                $originalSubtotal,
                'customer_expected_subtotal' =>
                $customerExpectedSubtotal,
                'proposed_subtotal' => null,
                'currency_code' =>
                (string)$cart->getQuoteCurrencyCode(),
                'expires_at' => null
            ]
        );

        return $this
            ->quoteRequestRepository
            ->save(
                $quoteRequest
            );
    }

    /**
     * @param array{
     *     requested_qty: float,
     *     original_price: float,
     *     expected_price: float|null,
     *     original_row_total: float,
     *     expected_row_total: float|null
     * } $itemData
     */
    private function createQuoteRequestItem(
        QuoteRequest $quoteRequest,
        CartItem $cartItem,
        array $itemData
    ): QuoteRequestItem {
        $requestItem = $this
            ->quoteRequestItemFactory
            ->create();

        $requestItem->setData(
            [
                'quote_request_id' =>
                (int)$quoteRequest->getId(),

                'product_id' =>
                $cartItem->getProductId()
                    ? (int)$cartItem->getProductId()
                    : null,

                'sku' =>
                (string)$cartItem->getSku(),

                'product_name' =>
                (string)$cartItem->getName(),

                'requested_qty' =>
                $itemData['requested_qty'],

                'original_price' =>
                $itemData['original_price'],

                'expected_price' =>
                $itemData['expected_price'],

                'proposed_price' => null,

                'original_row_total' =>
                $itemData['original_row_total'],

                'expected_row_total' =>
                $itemData['expected_row_total'],

                'proposed_row_total' => null,

                'product_options' =>
                $this->serializeProductOptions(
                    $cartItem
                )
            ]
        );

        return $this
            ->itemRepository
            ->save(
                $requestItem
            );
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

            if (
                $option
                && $option->getValue() !== null
            ) {
                $options[$optionCode] =
                    $option->getValue();
            }
        }

        if ($options === []) {
            return null;
        }

        try {
            return $this
                ->jsonSerializer
                ->serialize(
                    $options
                );
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
