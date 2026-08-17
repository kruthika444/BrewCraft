<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Block\Request;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as CartItem;

class Create extends Template
{
    private ?Quote $cart = null;

    private bool $cartLoaded = false;

    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PriceHelper $priceHelper,
        private readonly ImageHelper $imageHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Quote request form submit URL.
     */
    public function getFormAction(): string
    {
        return $this->getUrl(
            'requestquote/request/save'
        );
    }

    /**
     * Shopping cart URL.
     */
    public function getBackToCartUrl(): string
    {
        return $this->getUrl(
            'checkout/cart'
        );
    }

    /**
     * Get all visible cart items.
     *
     * @return CartItem[]
     */
    public function getCartItems(): array
    {
        return $this->getCart()?->getAllVisibleItems() ?? [];
    }

    /**
     * Calculate current cart subtotal using the current
     * Magento calculation price and cart quantity.
     */
    public function getCartSubtotal(): float
    {
        $subtotal = 0.0;

        foreach ($this->getCartItems() as $item) {
            $subtotal +=
                (float)$item->getCalculationPrice()
                * (float)$item->getQty();
        }

        return round(
            $subtotal,
            4
        );
    }

    /**
     * Format a price using the current Magento currency.
     */
    public function formatPrice(float $price): string
    {
        return $this->priceHelper->currency(
            $price,
            true,
            false
        );
    }

    /**
     * Get cart/quote item product image URL.
     */
    public function getProductImageUrl(CartItem $item): string
    {
        return $this->imageHelper
            ->init(
                $item->getProduct(),
                'cart_page_product_thumbnail'
            )
            ->getUrl();
    }

    /**
     * Load active cart for logged-in customer.
     */
    private function getCart(): ?Quote
    {
        if ($this->cartLoaded) {
            return $this->cart;
        }

        $this->cartLoaded = true;

        $customerId = (int)$this->customerSession->getCustomerId();

        if ($customerId <= 0) {
            return null;
        }

        try {
            $this->cart = $this->cartRepository->getActiveForCustomer(
                $customerId
            );
        } catch (\Throwable) {
            $this->cart = null;
        }

        return $this->cart;
    }
}
