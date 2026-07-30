<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Block\Account;

use BrewCraft\BusinessAccount\Api\BusinessAccountRepositoryInterface;
use BrewCraft\BusinessAccount\Model\BusinessAccount;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Index extends Template
{
    private bool $applicationLoaded = false;

    private ?BusinessAccount $businessAccount = null;

    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly BusinessAccountRepositoryInterface $businessAccountRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Return the logged-in customer's business application.
     */
    public function getBusinessAccount(): ?BusinessAccount
    {
        if ($this->applicationLoaded) {
            return $this->businessAccount;
        }

        $this->applicationLoaded = true;

        $customerId = (int)$this->customerSession->getCustomerId();

        if ($customerId <= 0) {
            return null;
        }

        try {
            $this->businessAccount = $this
                ->businessAccountRepository
                ->getByCustomerId($customerId);
        } catch (NoSuchEntityException) {
            $this->businessAccount = null;
        } catch (\Throwable) {
            /*
             * Do not expose internal database or repository errors
             * on the storefront.
             */
            $this->businessAccount = null;
        }

        return $this->businessAccount;
    }

    public function hasBusinessApplication(): bool
    {
        return $this->getBusinessAccount() !== null;
    }

    public function isPending(): bool
    {
        return $this->getBusinessAccount()?->isPending() === true;
    }

    public function isApproved(): bool
    {
        return $this->getBusinessAccount()?->isApproved() === true;
    }

    public function isRejected(): bool
    {
        return $this->getBusinessAccount()?->isRejected() === true;
    }

    public function getStatusLabel(): string
    {
        $businessAccount = $this->getBusinessAccount();

        if (!$businessAccount) {
            return (string)__('Not Applied');
        }

        return match ($businessAccount->getStatus()) {
            BusinessAccount::STATUS_APPROVED =>
                (string)__('Approved'),

            BusinessAccount::STATUS_REJECTED =>
                (string)__('Rejected'),

            default =>
                (string)__('Pending Review')
        };
    }

    public function getStatusCssClass(): string
    {
        $businessAccount = $this->getBusinessAccount();

        if (!$businessAccount) {
            return 'not-applied';
        }

        return match ($businessAccount->getStatus()) {
            BusinessAccount::STATUS_APPROVED => 'approved',
            BusinessAccount::STATUS_REJECTED => 'rejected',
            default => 'pending'
        };
    }

    public function getApplicationUrl(): string
    {
        return $this->getUrl(
            'businessaccount/account/create'
        );
    }

    public function getCustomerAccountUrl(): string
    {
        return $this->getUrl(
            'customer/account'
        );
    }

    public function getContinueShoppingUrl(): string
    {
        return $this->getUrl('');
    }

    public function displayNullableValue(
        mixed $value
    ): string {
        $value = trim((string)$value);

        return $value !== ''
            ? $value
            : (string)__('Not provided');
    }

    public function formatApplicationDate(
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

    /**
     * Return a customer-safe status explanation.
     */
    public function getStatusDescription(): string
    {
        if ($this->isApproved()) {
            return (string)__(
                'Your BrewCraft Business Account is active. You can access business services available to approved customers.'
            );
        }

        if ($this->isRejected()) {
            return (string)__(
                'Your business application was not approved. Your regular customer account remains active.'
            );
        }

        if ($this->isPending()) {
            return (string)__(
                'Your application has been submitted and is currently being reviewed by our business team.'
            );
        }

        return (string)__(
            'You have not submitted a BrewCraft Business Account application yet.'
        );
    }
}
