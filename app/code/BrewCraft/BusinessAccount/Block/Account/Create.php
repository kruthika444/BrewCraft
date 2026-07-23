<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Block\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\ResourceModel\Country\Collection as CountryCollection;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Create extends Template
{
    private ?CountryCollection $countryCollection = null;

    public function __construct(
        Context $context,
        private readonly FormKey $formKey,
        private readonly CustomerSession $customerSession,
        private readonly CountryCollectionFactory $countryCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Return the form submission URL.
     */
    public function getFormAction(): string
    {
        return $this->getUrl(
            'businessaccount/account/save'
        );
    }

    /**
     * Return Magento's form key.
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Check whether a customer is currently logged in.
     */
    public function isCustomerLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Return logged-in customer first name.
     */
    public function getCustomerFirstname(): string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        return (string)$this->customerSession
            ->getCustomer()
            ->getFirstname();
    }

    /**
     * Return logged-in customer last name.
     */
    public function getCustomerLastname(): string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        return (string)$this->customerSession
            ->getCustomer()
            ->getLastname();
    }

    /**
     * Return logged-in customer email.
     */
    public function getCustomerEmail(): string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        return (string)$this->customerSession
            ->getCustomer()
            ->getEmail();
    }

    /**
     * Return available countries for the address field.
     */
    public function getCountryOptions(): array
    {
        if ($this->countryCollection === null) {
            $this->countryCollection = $this
                ->countryCollectionFactory
                ->create()
                ->loadByStore();
        }

        return $this->countryCollection->toOptionArray();
    }

    /**
     * Return available company types.
     */
    public function getCompanyTypes(): array
    {
        return [
            'sole_proprietorship' => __('Sole Proprietorship'),
            'partnership' => __('Partnership'),
            'private_limited' => __('Private Limited Company'),
            'public_limited' => __('Public Limited Company'),
            'limited_liability' => __('Limited Liability Company'),
            'non_profit' => __('Non-Profit Organization'),
            'government' => __('Government Organization'),
            'other' => __('Other')
        ];
    }
}
