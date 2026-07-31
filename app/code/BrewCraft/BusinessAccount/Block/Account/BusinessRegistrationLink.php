<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Block\Account;

use BrewCraft\BusinessAccount\Api\BusinessAccountRepositoryInterface;
use BrewCraft\BusinessAccount\Model\BusinessAccount;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Psr\Log\LoggerInterface;

class BusinessRegistrationLink extends Template
{
    private bool $applicationLoaded = false;

    private ?BusinessAccount $businessAccount = null;

    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly BusinessAccountRepositoryInterface $businessAccountRepository,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isCustomerLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    public function getBusinessAccount(): ?BusinessAccount
    {
        if ($this->applicationLoaded) {
            return $this->businessAccount;
        }

        $this->applicationLoaded = true;

        if (!$this->customerSession->isLoggedIn()) {
            return null;
        }

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
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Unable to load Business Account application for storefront CTA.',
                [
                    'customer_id' => $customerId,
                    'exception' => $exception->getMessage()
                ]
            );

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

    public function getActionUrl(): string
    {
        if ($this->hasBusinessApplication()) {
            return $this->getUrl('businessaccount/account/index');
        }

        return $this->getUrl('businessaccount/account/create');
    }

    public function getActionLabel(): string
    {
        if ($this->isApproved()) {
            return (string)__('Go to Business Account');
        }

        if ($this->isRejected()) {
            return (string)__('View Review Feedback');
        }

        if ($this->isPending()) {
            return (string)__('View Application Status');
        }

        return (string)__('Apply for a Business Account');
    }

    public function getHeading(): string
    {
        if ($this->isApproved()) {
            return (string)__('Your Business Account Is Active');
        }

        if ($this->isRejected()) {
            return (string)__('Your Business Application Was Reviewed');
        }

        if ($this->isPending()) {
            return (string)__('Your Application Is Under Review');
        }

        return (string)__('Registering for Your Business?');
    }

    public function getDescription(): string
    {
        if ($this->isApproved()) {
            return (string)__(
                'Your BrewCraft Business Account has been approved. Open your Business Account page to review your company information and account status.'
            );
        }

        if ($this->isRejected()) {
            return (string)__(
                'Your business application was not approved. Open your Business Account page to view the review feedback. Contact our support team if you need another review.'
            );
        }

        if ($this->isPending()) {
            return (string)__(
                'Your business application has already been submitted and is currently under review.'
            );
        }

        return (string)__(
            'Apply for a BrewCraft Business Account to access wholesale pricing, quotation services and other business benefits after approval.'
        );
    }

    public function shouldShowBenefits(): bool
    {
        return !$this->hasBusinessApplication();
    }

    public function getCustomerLoginUrl(): string
    {
        return $this->getUrl('customer/account/login');
    }

    public function getContextType(): string
    {
        return (string)$this->getData('context_type');
    }

    public function isLoginPage(): bool
    {
        return $this->getContextType() === 'login';
    }
}
