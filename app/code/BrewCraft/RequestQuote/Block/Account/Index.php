<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Block\Account;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequest\Collection;
use BrewCraft\RequestQuote\Model\Source\Status;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Theme\Block\Html\Pager;

class Index extends Template
{
    private bool $collectionLoaded = false;

    private ?Collection $quoteCollection = null;

    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly Status $statusSource,
        private readonly PriceHelper $priceHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _prepareLayout(): static
    {
        parent::_prepareLayout();

        $collection = $this->getQuoteCollection();

        if ($collection !== null) {
            /** @var Pager $pager */
            $pager = $this->getLayout()->createBlock(
                Pager::class,
                'brewcraft.requestquote.account.pager'
            );

            $pager->setAvailableLimit(
                [
                    5 => 5,
                    10 => 10,
                    20 => 20
                ]
            );

            $pager->setShowPerPage(true);
            $pager->setCollection($collection);

            $this->setChild(
                'pager',
                $pager
            );
        }

        return $this;
    }

    public function getQuoteCollection(): ?Collection
    {
        if ($this->collectionLoaded) {
            return $this->quoteCollection;
        }

        $this->collectionLoaded = true;

        $customerId = (int)$this->customerSession->getCustomerId();

        if ($customerId <= 0) {
            return null;
        }

        $this->quoteCollection = $this
            ->quoteRequestRepository
            ->getByCustomerId($customerId);

        return $this->quoteCollection;
    }

    public function hasQuotes(): bool
    {
        return $this->getQuoteCollection()?->getSize() > 0;
    }

    public function getPagerHtml(): string
    {
        return $this->getChildHtml('pager');
    }

    public function getViewUrl(
        string $quoteNumber
    ): string {
        return $this->getUrl(
            'requestquote/account/view',
            [
                'quote_number' => $quoteNumber
            ]
        );
    }

    public function getRequestQuoteUrl(): string
    {
        return $this->getUrl(
            'checkout/cart'
        );
    }

    public function getStatusLabel(
        string $status
    ): string {
        return $this->statusSource->getLabel($status);
    }

    public function getStatusCssClass(
        string $status
    ): string {
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

    public function formatPrice(
        float $price
    ): string {
        return $this->priceHelper->currency(
            $price,
            true,
            false
        );
    }

    public function formatDateValue(
        mixed $date
    ): string {
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
}
