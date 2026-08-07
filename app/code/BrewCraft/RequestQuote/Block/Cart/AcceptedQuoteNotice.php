<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class AcceptedQuoteNotice extends Template
{
    public function __construct(
        Context $context,
        private readonly CheckoutSession $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isAcceptedQuoteCart(): bool
    {
        return (int)$this->checkoutSession
            ->getQuote()
            ->getData('brewcraft_quote_request_id') > 0;
    }
}
