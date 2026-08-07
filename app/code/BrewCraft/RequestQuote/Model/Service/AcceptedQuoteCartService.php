<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model\Service;

use BrewCraft\RequestQuote\Api\QuoteRequestItemRepositoryInterface;
use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\QuoteRequest;
use BrewCraft\RequestQuote\Model\QuoteRequestItem;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as CartItem;
use Psr\Log\LoggerInterface;

class AcceptedQuoteCartService
{
    public function __construct(
        private readonly BusinessCustomerEligibilityService $eligibilityService,
        private readonly QuoteRequestItemRepositoryInterface $itemRepository,
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly DataObjectFactory $dataObjectFactory,
        private readonly Json $jsonSerializer,
        private readonly CheckoutSession $checkoutSession,
        private readonly LoggerInterface $logger
    ) {
    }

    public function convertToCart(QuoteRequest $quoteRequest, int $customerId): Quote
    {
        $this->validateQuote($quoteRequest, $customerId);
        $this->eligibilityService->validate($customerId);

        $quoteItems = $this->itemRepository->getByQuoteRequestId(
            (int)$quoteRequest->getId()
        );

        if ($quoteItems->getSize() === 0) {
            throw new LocalizedException(
                __('This accepted quote does not contain any items.')
            );
        }

        $cart = $this->getOrCreateActiveCart($customerId);

        try {
            foreach ($cart->getAllItems() as $cartItem) {
                $cart->removeItem((int)$cartItem->getId());
            }

            foreach ($quoteItems as $quoteItem) {
                $this->addQuoteItemToCart($cart, $quoteItem);
            }

            $cart->setData(
                'brewcraft_quote_request_id',
                (int)$quoteRequest->getId()
            );
            $cart->setCouponCode('');
            $cart->setTotalsCollectedFlag(false);
            $cart->collectTotals();

            $this->cartRepository->save($cart);

            $reloadedCart = $this->cartRepository->getActive(
                (int)$cart->getId()
            );
            $reloadedCart->setTotalsCollectedFlag(false);
            $reloadedCart->collectTotals();
            $this->cartRepository->save($reloadedCart);

            $this->checkoutSession->replaceQuote($reloadedCart);

            $quoteRequest->setData(
                'checkout_quote_id',
                (int)$reloadedCart->getId()
            );
            $this->quoteRequestRepository->save($quoteRequest);

            return $reloadedCart;
        } catch (LocalizedException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Unable to load BrewCraft accepted quote into cart.',
                [
                    'quote_request_id' => (int)$quoteRequest->getId(),
                    'quote_number' => $quoteRequest->getQuoteNumber(),
                    'customer_id' => $customerId,
                    'exception' => $exception->getMessage()
                ]
            );

            throw new LocalizedException(
                __('The accepted quote could not be loaded into your shopping cart.')
            );
        }
    }

    private function validateQuote(QuoteRequest $quoteRequest, int $customerId): void
    {
        if (!$quoteRequest->getId()) {
            throw new LocalizedException(__('The quote request does not exist.'));
        }

        if ($customerId <= 0) {
            throw new LocalizedException(
                __('You must be logged in to continue with this quote.')
            );
        }

        if ($quoteRequest->getCustomerId() !== $customerId) {
            throw new LocalizedException(
                __('You are not allowed to convert this quote into a cart.')
            );
        }

        if ($quoteRequest->getStatus() !== QuoteRequest::STATUS_ACCEPTED) {
            throw new LocalizedException(
                __(
                    'Only an accepted quote can be loaded into the shopping cart. Current status: %1.',
                    $quoteRequest->getStatus()
                )
            );
        }

        if (
            (int)$quoteRequest->getData('order_id') > 0
            || trim((string)$quoteRequest->getData('order_increment_id')) !== ''
        ) {
            throw new LocalizedException(
                __('This quotation has already been converted into an order.')
            );
        }

        if ($quoteRequest->getData('proposed_subtotal') === null) {
            throw new LocalizedException(
                __('This accepted quote does not contain a price proposal.')
            );
        }

        $expiresAt = trim((string)$quoteRequest->getData('expires_at'));

        if ($expiresAt !== '') {
            $expiryTimestamp = strtotime($expiresAt);

            if ($expiryTimestamp === false || $expiryTimestamp < time()) {
                throw new LocalizedException(
                    __('This accepted quote has expired.')
                );
            }
        }
    }

    private function getOrCreateActiveCart(int $customerId): Quote
    {
        try {
            return $this->cartRepository->getActiveForCustomer($customerId);
        } catch (NoSuchEntityException) {
            $cartId = $this->cartManagement->createEmptyCartForCustomer($customerId);

            return $this->cartRepository->get($cartId);
        }
    }

    private function addQuoteItemToCart(
        Quote $cart,
        QuoteRequestItem $quoteItem
    ): void {
        $productId = (int)$quoteItem->getProductId();
        $requestedQty = (float)$quoteItem->getRequestedQty();
        $proposedPrice = $quoteItem->getData('proposed_price');

        if ($productId <= 0) {
            throw new LocalizedException(
                __('The product %1 is no longer available.', $quoteItem->getSku())
            );
        }

        if ($requestedQty <= 0) {
            throw new LocalizedException(
                __('The quoted quantity for %1 is invalid.', $quoteItem->getSku())
            );
        }

        if (
            $proposedPrice === null
            || !is_numeric($proposedPrice)
            || (float)$proposedPrice <= 0
        ) {
            throw new LocalizedException(
                __('The negotiated price for %1 is missing or invalid.', $quoteItem->getSku())
            );
        }

        try {
            $product = $this->productRepository->getById(
                $productId,
                false,
                (int)$cart->getStoreId(),
                true
            );
        } catch (NoSuchEntityException) {
            throw new LocalizedException(
                __('The product %1 no longer exists.', $quoteItem->getSku())
            );
        }

        if (!$product->isSalable()) {
            throw new LocalizedException(
                __('The product %1 is currently unavailable for purchase.', $quoteItem->getSku())
            );
        }

        $result = $cart->addProduct(
            $product,
            $this->buildBuyRequest($quoteItem, $requestedQty)
        );

        if (is_string($result)) {
            throw new LocalizedException(
                __(
                    'The product %1 could not be added to the cart: %2',
                    $quoteItem->getSku(),
                    $result
                )
            );
        }

        if (!$result instanceof CartItem) {
            throw new LocalizedException(
                __('The product %1 could not be added to the cart.', $quoteItem->getSku())
            );
        }

        $negotiatedPrice = round((float)$proposedPrice, 4);

        $result->setData(
            'brewcraft_quote_request_item_id',
            (int)$quoteItem->getId()
        );
        $result->setData('brewcraft_locked_qty', $requestedQty);
        $result->setData('brewcraft_negotiated_price', $negotiatedPrice);
        $result->setQty($requestedQty);
        $result->setCustomPrice($negotiatedPrice);
        $result->setOriginalCustomPrice($negotiatedPrice);
        $result->setNoDiscount(true);
        $result->getProduct()->setIsSuperMode(true);
    }

    private function buildBuyRequest(
        QuoteRequestItem $quoteItem,
        float $requestedQty
    ): DataObject {
        $requestData = [];
        $storedOptions = trim((string)$quoteItem->getData('product_options'));

        if ($storedOptions !== '') {
            try {
                $decodedOptions = $this->jsonSerializer->unserialize($storedOptions);

                if (
                    is_array($decodedOptions)
                    && isset($decodedOptions['info_buyRequest'])
                ) {
                    $buyRequestData = $decodedOptions['info_buyRequest'];

                    if (is_string($buyRequestData)) {
                        try {
                            $buyRequestData = $this->jsonSerializer->unserialize(
                                $buyRequestData
                            );
                        } catch (\InvalidArgumentException) {
                            $buyRequestData = [];
                        }
                    }

                    if (is_array($buyRequestData)) {
                        $requestData = $buyRequestData;
                    }
                }
            } catch (\InvalidArgumentException) {
                $requestData = [];
            }
        }

        $requestData['qty'] = $requestedQty;

        return $this->dataObjectFactory->create([
            'data' => $requestData
        ]);
    }
}
