<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model;

use Magento\Framework\Model\AbstractModel;

class QuoteRequest extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_QUOTED = 'quoted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected function _construct(): void
    {
        $this->_init(
            \BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequest::class
        );
    }

    public function isPending(): bool
    {
        return $this->getStatus() === self::STATUS_PENDING;
    }

    public function isUnderReview(): bool
    {
        return $this->getStatus() === self::STATUS_UNDER_REVIEW;
    }

    public function isQuoted(): bool
    {
        return $this->getStatus() === self::STATUS_QUOTED;
    }

    public function isAccepted(): bool
    {
        return $this->getStatus() === self::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->getStatus() === self::STATUS_REJECTED;
    }

    public function isConverted(): bool
    {
        return $this->getStatus() === self::STATUS_CONVERTED;
    }

    public function isCancelled(): bool
    {
        return $this->getStatus() === self::STATUS_CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->getStatus() === self::STATUS_EXPIRED;
    }

    public function getStatus(): string
    {
        return (string)$this->getData('status');
    }

    public function getQuoteNumber(): string
    {
        return (string)$this->getData('quote_number');
    }

    public function getCustomerId(): int
    {
        return (int)$this->getData('customer_id');
    }

    public function getBusinessAccountId(): int
    {
        return (int)$this->getData('business_account_id');
    }
}
