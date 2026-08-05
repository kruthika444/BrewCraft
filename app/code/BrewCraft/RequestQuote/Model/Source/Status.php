<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model\Source;

use BrewCraft\RequestQuote\Model\QuoteRequest;
use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => QuoteRequest::STATUS_PENDING,
                'label' => __('Pending')
            ],
            [
                'value' => QuoteRequest::STATUS_UNDER_REVIEW,
                'label' => __('Under Review')
            ],
            [
                'value' => QuoteRequest::STATUS_QUOTED,
                'label' => __('Quoted')
            ],
            [
                'value' => QuoteRequest::STATUS_ACCEPTED,
                'label' => __('Accepted')
            ],
            [
                'value' => QuoteRequest::STATUS_REJECTED,
                'label' => __('Rejected')
            ],
            [
                'value' => QuoteRequest::STATUS_CONVERTED,
                'label' => __('Converted')
            ],
            [
                'value' => QuoteRequest::STATUS_CANCELLED,
                'label' => __('Cancelled')
            ],
            [
                'value' => QuoteRequest::STATUS_EXPIRED,
                'label' => __('Expired')
            ]
        ];
    }

    public function getLabel(string $status): string
    {
        foreach ($this->toOptionArray() as $option) {
            if ($option['value'] === $status) {
                return (string)$option['label'];
            }
        }

        return (string)__('Unknown');
    }
}
