<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Block\Request;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\QuoteRequest;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Success extends Template
{
    private bool $quoteLoaded = false;

    private ?QuoteRequest $quoteRequest = null;

    public function __construct(
        Context $context,
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getQuoteRequest(): ?QuoteRequest
    {
        if ($this->quoteLoaded) {
            return $this->quoteRequest;
        }

        $this->quoteLoaded = true;

        $quoteNumber = trim(
            (string)$this->getRequest()->getParam(
                'quote_number'
            )
        );

        if ($quoteNumber === '') {
            return null;
        }

        try {
            $this->quoteRequest = $this
                ->quoteRequestRepository
                ->getByQuoteNumber($quoteNumber);
        } catch (NoSuchEntityException) {
            $this->quoteRequest = null;
        }

        return $this->quoteRequest;
    }

    public function getContinueShoppingUrl(): string
    {
        return $this->getUrl('');
    }

    public function getCustomerAccountUrl(): string
    {
        return $this->getUrl(
            'customer/account'
        );
    }
}
