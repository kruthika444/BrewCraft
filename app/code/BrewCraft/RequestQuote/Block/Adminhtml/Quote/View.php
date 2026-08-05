<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Block\Adminhtml\Quote;

use BrewCraft\BusinessAccount\Api\BusinessAccountRepositoryInterface;
use BrewCraft\BusinessAccount\Model\BusinessAccount;
use BrewCraft\RequestQuote\Api\QuoteRequestItemRepositoryInterface;
use BrewCraft\RequestQuote\Controller\Adminhtml\Quote\View as ViewController;
use BrewCraft\RequestQuote\Model\QuoteRequest;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequestItem\Collection;
use BrewCraft\RequestQuote\Model\Source\Status;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Registry;

class View extends Template
{
    private bool $itemsLoaded = false;

    private ?Collection $itemCollection = null;

    private bool $customerLoaded = false;

    private ?CustomerInterface $customer = null;

    private bool $businessAccountLoaded = false;

    private ?BusinessAccount $businessAccount = null;

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly QuoteRequestItemRepositoryInterface $itemRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly BusinessAccountRepositoryInterface $businessAccountRepository,
        private readonly Status $statusSource,
        private readonly PriceHelper $priceHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getQuoteRequest(): ?QuoteRequest
    {
        $quoteRequest = $this->registry->registry(
            ViewController::REGISTRY_KEY
        );

        return $quoteRequest instanceof QuoteRequest
            ? $quoteRequest
            : null;
    }

    public function getQuoteItems(): ?Collection
    {
        if ($this->itemsLoaded) {
            return $this->itemCollection;
        }

        $this->itemsLoaded = true;

        $quoteRequest = $this->getQuoteRequest();

        if (!$quoteRequest || (int)$quoteRequest->getId() <= 0) {
            return null;
        }

        $this->itemCollection = $this
            ->itemRepository
            ->getByQuoteRequestId(
                (int)$quoteRequest->getId()
            );

        return $this->itemCollection;
    }

    public function getCustomer(): ?CustomerInterface
    {
        if ($this->customerLoaded) {
            return $this->customer;
        }

        $this->customerLoaded = true;

        $quoteRequest = $this->getQuoteRequest();

        if (!$quoteRequest || $quoteRequest->getCustomerId() <= 0) {
            return null;
        }

        try {
            $this->customer = $this->customerRepository->getById(
                $quoteRequest->getCustomerId()
            );
        } catch (\Throwable) {
            $this->customer = null;
        }

        return $this->customer;
    }

    public function getBusinessAccount(): ?BusinessAccount
    {
        if ($this->businessAccountLoaded) {
            return $this->businessAccount;
        }

        $this->businessAccountLoaded = true;

        $quoteRequest = $this->getQuoteRequest();

        if (!$quoteRequest || $quoteRequest->getCustomerId() <= 0) {
            return null;
        }

        try {
            $businessAccount = $this
                ->businessAccountRepository
                ->getByCustomerId(
                    $quoteRequest->getCustomerId()
                );

            $this->businessAccount = $businessAccount instanceof BusinessAccount
                ? $businessAccount
                : null;
        } catch (\Throwable) {
            $this->businessAccount = null;
        }

        return $this->businessAccount;
    }

    public function getCustomerName(): string
    {
        $customer = $this->getCustomer();

        if (!$customer) {
            return (string)__('Not available');
        }

        return trim(
            (string)$customer->getFirstname()
                . ' '
                . (string)$customer->getLastname()
        );
    }

    public function getCustomerEmail(): string
    {
        return (string)(
            $this->getCustomer()?->getEmail()
            ?? __('Not available')
        );
    }

    public function getStatusLabel(string $status): string
    {
        return $this->statusSource->getLabel($status);
    }

    public function formatPrice(float $price): string
    {
        return $this->priceHelper->currency(
            $price,
            true,
            false
        );
    }

    public function formatDateValue(mixed $date): string
    {
        $date = trim((string)$date);

        if ($date === '') {
            return (string)__('Not available');
        }

        try {
            return $this->formatDate(
                $date,
                \IntlDateFormatter::MEDIUM,
                true
            );
        } catch (\Throwable) {
            return $date;
        }
    }

    public function getBackUrl(): string
    {
        return $this->getUrl(
            'requestquote/quote/index'
        );
    }
    public function canMarkUnderReview(): bool
    {
        $quoteRequest = $this->getQuoteRequest();

        return $quoteRequest !== null
            && $quoteRequest->getStatus()
            === QuoteRequest::STATUS_PENDING;
    }

    public function getMarkUnderReviewUrl(): string
    {
        $quoteRequest = $this->getQuoteRequest();

        if (!$quoteRequest || (int)$quoteRequest->getId() <= 0) {
            return '';
        }

        return $this->getUrl(
            'requestquote/quote/markUnderReview',
            [
                'id' => (int)$quoteRequest->getId()
            ]
        );
    }
    public function canCreateProposal(): bool
    {
        $quoteRequest = $this->getQuoteRequest();

        return $quoteRequest !== null
            && $quoteRequest->getStatus()
            === QuoteRequest::STATUS_UNDER_REVIEW;
    }

    public function getSaveProposalUrl(): string
    {
        $quoteRequest = $this->getQuoteRequest();

        if (!$quoteRequest || (int)$quoteRequest->getId() <= 0) {
            return '';
        }

        return $this->getUrl(
            'requestquote/quote/saveProposal',
            [
                'id' => (int)$quoteRequest->getId()
            ]
        );
    }

    public function getCurrencyCode(): string
    {
        $quoteRequest = $this->getQuoteRequest();

        return $quoteRequest
            ? (string)$quoteRequest->getData('currency_code')
            : '';
    }

    public function getExpiryDateInputValue(): string
    {
        $quoteRequest = $this->getQuoteRequest();

        if (!$quoteRequest) {
            return '';
        }

        $expiresAt = trim(
            (string)$quoteRequest->getData('expires_at')
        );

        if ($expiresAt === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($expiresAt))
                ->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }
}
