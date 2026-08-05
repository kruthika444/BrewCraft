<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model\Service;

use BrewCraft\BusinessAccount\Api\BusinessAccountRepositoryInterface;
use BrewCraft\BusinessAccount\Model\BusinessAccount;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class BusinessCustomerEligibilityService
{
    private const BUSINESS_GROUP_CODE = 'Business Customer';

    public function __construct(
        private readonly BusinessAccountRepositoryInterface $businessAccountRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly GroupRepositoryInterface $groupRepository
    ) {
    }

    /**
     * Return the approved Business Account application.
     *
     * @throws LocalizedException
     */
    public function validate(int $customerId): BusinessAccount
    {
        if ($customerId <= 0) {
            throw new LocalizedException(
                __('You must be logged in to request a quote.')
            );
        }

        try {
            $businessAccount = $this
                ->businessAccountRepository
                ->getByCustomerId($customerId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(
                __(
                    'Only approved Business Account customers can request a quote.'
                )
            );
        }

        if (!$businessAccount->isApproved()) {
            throw new LocalizedException(
                __(
                    'Your Business Account must be approved before you can request a quote.'
                )
            );
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $group = $this->groupRepository->getById(
                (int)$customer->getGroupId()
            );
        } catch (NoSuchEntityException) {
            throw new LocalizedException(
                __('Your customer account or customer group could not be found.')
            );
        }

        if ($group->getCode() !== self::BUSINESS_GROUP_CODE) {
            throw new LocalizedException(
                __(
                    'Your customer account is not assigned to the Business Customer group.'
                )
            );
        }

        return $businessAccount;
    }

    public function isEligible(int $customerId): bool
    {
        try {
            $this->validate($customerId);

            return true;
        } catch (LocalizedException) {
            return false;
        }
    }
}
