<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Model\Source;

use BrewCraft\BusinessAccount\Model\BusinessAccount;
use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    /**
     * Return status options for Admin grid filters.
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => BusinessAccount::STATUS_PENDING,
                'label' => __('Pending')
            ],
            [
                'value' => BusinessAccount::STATUS_APPROVED,
                'label' => __('Approved')
            ],
            [
                'value' => BusinessAccount::STATUS_REJECTED,
                'label' => __('Rejected')
            ]
        ];
    }
}