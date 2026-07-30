<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Block\Adminhtml\Application;

use BrewCraft\BusinessAccount\Controller\Adminhtml\Application\View as ViewController;
use BrewCraft\BusinessAccount\Model\BusinessAccount;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;

class View extends Template
{
    private ?CustomerInterface $customer = null;

    private bool $customerLoaded = false;

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly CustomerRepositoryInterface $customerRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getBusinessAccount(): ?BusinessAccount
    {
        $businessAccount = $this->registry->registry(
            ViewController::REGISTRY_KEY
        );

        return $businessAccount instanceof BusinessAccount
            ? $businessAccount
            : null;
    }

    public function getCustomer(): ?CustomerInterface
    {
        if ($this->customerLoaded) {
            return $this->customer;
        }

        $this->customerLoaded = true;

        $businessAccount = $this->getBusinessAccount();

        if (!$businessAccount || !$businessAccount->getCustomerId()) {
            return null;
        }

        try {
            $this->customer = $this->customerRepository->getById(
                (int)$businessAccount->getCustomerId()
            );
        } catch (NoSuchEntityException) {
            $this->customer = null;
        } catch (\Throwable) {
            $this->customer = null;
        }

        return $this->customer;
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getBackUrl(): string
    {
        return $this->getUrl(
            'businessaccount/application/index'
        );
    }

    public function getApproveUrl(): string
    {
        $businessAccount = $this->getBusinessAccount();

        return $this->getUrl(
            'businessaccount/application/approve',
            [
                'entity_id' => $businessAccount?->getEntityId()
            ]
        );
    }

    public function getRejectUrl(): string
    {
        $businessAccount = $this->getBusinessAccount();

        return $this->getUrl(
            'businessaccount/application/reject',
            [
                'entity_id' => $businessAccount?->getEntityId()
            ]
        );
    }

    public function getCustomerEditUrl(): ?string
    {
        $customer = $this->getCustomer();

        if (!$customer || !$customer->getId()) {
            return null;
        }

        return $this->getUrl(
            'customer/index/edit',
            [
                'id' => (int)$customer->getId()
            ]
        );
    }

    public function getStatusLabel(): string
    {
        $businessAccount = $this->getBusinessAccount();

        if (!$businessAccount) {
            return '';
        }

        return match ($businessAccount->getStatus()) {
            BusinessAccount::STATUS_APPROVED =>
                (string)__('Approved'),

            BusinessAccount::STATUS_REJECTED =>
                (string)__('Rejected'),

            default =>
                (string)__('Pending')
        };
    }

    public function getStatusCssClass(): string
    {
        $businessAccount = $this->getBusinessAccount();

        if (!$businessAccount) {
            return 'pending';
        }

        return match ($businessAccount->getStatus()) {
            BusinessAccount::STATUS_APPROVED => 'approved',
            BusinessAccount::STATUS_REJECTED => 'rejected',
            default => 'pending'
        };
    }

    public function isPending(): bool
    {
        return $this->getBusinessAccount()?->isPending() === true;
    }

    public function formatApplicationDate(
        ?string $date
    ): string {
        if (!$date) {
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

    public function displayNullableValue(
        mixed $value
    ): string {
        $value = trim((string)$value);

        return $value !== ''
            ? $value
            : (string)__('Not provided');
    }
}