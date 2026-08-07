<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Block\Account;

use BrewCraft\RequestQuote\Api\QuoteRequestItemRepositoryInterface;
use BrewCraft\RequestQuote\Controller\Account\View as ViewController;
use BrewCraft\RequestQuote\Model\QuoteRequest;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequestItem\Collection;
use BrewCraft\RequestQuote\Model\Source\Status;
use BrewCraft\RequestQuote\Model\Service\QuoteResponseService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class View extends Template
{
    private bool $itemsLoaded = false;
    private ?Collection $itemCollection = null;

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly QuoteRequestItemRepositoryInterface $itemRepository,
        private readonly Status $statusSource,
        private readonly PriceHelper $priceHelper,
        private readonly CustomerSession $customerSession,
        private readonly QuoteResponseService $quoteResponseService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getQuoteRequest(): ?QuoteRequest
    {
        $quoteRequest = $this->registry->registry(ViewController::REGISTRY_KEY);

        return $quoteRequest instanceof QuoteRequest ? $quoteRequest : null;
    }

    public function getQuoteItems(): ?Collection
    {
        if ($this->itemsLoaded) {
            return $this->itemCollection;
        }

        $this->itemsLoaded = true;
        $quoteRequest = $this->getQuoteRequest();

        if (!$quoteRequest || !(int)$quoteRequest->getId()) {
            return null;
        }

        $this->itemCollection = $this->itemRepository->getByQuoteRequestId(
            (int)$quoteRequest->getId()
        );

        return $this->itemCollection;
    }

    public function getStatusLabel(string $status): string
    {
        return $this->statusSource->getLabel($status);
    }

    public function getStatusCssClass(string $status): string
    {
        return match ($status) {
            'under_review' => 'under-review',
            'quoted' => 'quoted',
            'accepted' => 'accepted',
            'rejected' => 'rejected',
            'converted' => 'converted',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            default => 'pending'
        };
    }

    public function formatPrice(float $price): string
    {
        return $this->priceHelper->currency($price, true, false);
    }

    public function formatDateValue(mixed $date): string
    {
        $date = trim((string)$date);

        if ($date === '') {
            return (string)__('Not available');
        }

        try {
            return $this->formatDate($date, \IntlDateFormatter::MEDIUM, true);
        } catch (\Throwable) {
            return $date;
        }
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('requestquote/account/index');
    }

    public function getContinueShoppingUrl(): string
    {
        return $this->getUrl('');
    }

    public function canRespondToProposal(): bool
    {
        $quoteRequest = $this->getQuoteRequest();

        if (!$quoteRequest) {
            return false;
        }

        return $this->quoteResponseService->canRespond(
            $quoteRequest,
            (int)$this->customerSession->getCustomerId()
        );
    }

    public function getAcceptUrl(): string
    {
        $quoteRequest = $this->getQuoteRequest();

        if (!$quoteRequest || (int)$quoteRequest->getId() <= 0) {
            return '';
        }

        return $this->getUrl('requestquote/account/accept', [
            'id' => (int)$quoteRequest->getId()
        ]);
    }

    public function getRejectUrl(): string
    {
        $quoteRequest = $this->getQuoteRequest();

        if (!$quoteRequest || (int)$quoteRequest->getId() <= 0) {
            return '';
        }

        return $this->getUrl('requestquote/account/reject', [
            'id' => (int)$quoteRequest->getId()
        ]);
    }

    public function canConvertToCart(): bool
    {
        $quoteRequest = $this->getQuoteRequest();

        return $quoteRequest !== null
            && $quoteRequest->getStatus() === QuoteRequest::STATUS_ACCEPTED
            && !$this->isProposalExpired()
            && (int)$quoteRequest->getData('order_id') <= 0
            && trim((string)$quoteRequest->getData('order_increment_id')) === '';
    }

    public function getConvertToCartUrl(): string
    {
        $quoteRequest = $this->getQuoteRequest();

        if (!$quoteRequest || (int)$quoteRequest->getId() <= 0) {
            return '';
        }

        return $this->getUrl('requestquote/account/convertToCart', [
            'id' => (int)$quoteRequest->getId()
        ]);
    }

    public function isAccepted(): bool
    {
        return $this->getQuoteRequest()?->getStatus() === QuoteRequest::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->getQuoteRequest()?->getStatus() === QuoteRequest::STATUS_REJECTED;
    }

    public function isConverted(): bool
    {
        return $this->getQuoteRequest()?->getStatus() === QuoteRequest::STATUS_CONVERTED;
    }

    public function getOrderNumber(): string
    {
        return trim((string)$this->getQuoteRequest()?->getData('order_increment_id'));
    }

    public function getOrderViewUrl(): string
    {
        $orderId = (int)$this->getQuoteRequest()?->getData('order_id');

        return $orderId > 0
            ? $this->getUrl('sales/order/view', ['order_id' => $orderId])
            : '';
    }

    public function isProposalExpired(): bool
    {
        $quoteRequest = $this->getQuoteRequest();

        if (!$quoteRequest) {
            return false;
        }

        $expiresAt = trim((string)$quoteRequest->getData('expires_at'));

        if ($expiresAt === '') {
            return false;
        }

        $timestamp = strtotime($expiresAt);

        return $timestamp !== false && $timestamp < time();
    }
}
