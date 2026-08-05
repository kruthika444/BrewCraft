<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Block\Request;

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
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getFormAction(): string
    {
        return $this->getUrl(
            'requestquote/request/save'
        );
    }

    public function getBackToCartUrl(): string
    {
        return $this->getUrl(
            'checkout/cart'
        );
    }

    /**
     * @return CartItem[]
     */
    public function getCartItems(): array
    {
        return $this->getCart()?->getAllVisibleItems() ?? [];
    }

    public function getCartSubtotal(): float
    {
        $subtotal = 0.0;

        foreach ($this->getCartItems() as $item) {
            $subtotal +=
                (float)$item->getCalculationPrice()
                * (float)$item->getQty();
        }

        return round($subtotal, 4);
    }

    public function formatPrice(float $price): string
    {
        return $this->priceHelper->currency(
            $price,
            true,
            false
        );
    }

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
