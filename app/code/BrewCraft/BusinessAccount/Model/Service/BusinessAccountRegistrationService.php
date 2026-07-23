<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Model\Service;

use BrewCraft\BusinessAccount\Api\BusinessAccountRepositoryInterface;
use BrewCraft\BusinessAccount\Model\BusinessAccount;
use BrewCraft\BusinessAccount\Model\BusinessAccountFactory;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class BusinessAccountRegistrationService
{
    public function __construct(
        private readonly BusinessAccountRepositoryInterface $businessAccountRepository,
        private readonly BusinessAccountFactory $businessAccountFactory,
        private readonly CustomerSession $customerSession,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AccountManagementInterface $accountManagement,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Register a new BrewCraft business application.
     *
     * For logged-in customers, the application is attached to the
     * existing Magento customer.
     *
     * For guest visitors, a new Magento customer is created first.
     *
     * @throws LocalizedException
     */
    public function register(array $data): BusinessAccount
    {
        $data = $this->normalizeData($data);

        $this->validate($data);

        $this->validateRegistrationNumber(
            $data['registration_number']
        );

        $createdCustomer = null;

        if ($this->customerSession->isLoggedIn()) {
            $customerId = (int)$this->customerSession
                ->getCustomerId();

            $customer = $this->customerRepository->getById(
                $customerId
            );

            $this->validateCustomerApplication(
                $customerId
            );
        } else {
            $customer = $this->createCustomer($data);
            $createdCustomer = $customer;
            $customerId = (int)$customer->getId();
        }

        try {
            $businessAccount = $this->businessAccountFactory
                ->create();

            $businessAccount->setData([
                'customer_id' => $customerId,
                'company_name' => $data['company_name'],
                'registration_number' => $data['registration_number'],
                'tax_number' => $this->nullableValue(
                    $data['tax_number']
                ),
                'company_type' => $this->nullableValue(
                    $data['company_type']
                ),
                'business_years' => $this->nullableInteger(
                    $data['business_years']
                ),
                'contact_name' => $this->buildContactName($data),
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'],
                'street' => $data['street'],
                'city' => $data['city'],
                'region' => $this->nullableValue(
                    $data['region']
                ),
                'postcode' => $data['postcode'],
                'country_id' => strtoupper(
                    $data['country_id']
                ),
                'status' => BusinessAccount::STATUS_PENDING
            ]);

            $businessAccount = $this
                ->businessAccountRepository
                ->save($businessAccount);
        } catch (\Throwable $exception) {
            /*
             * If customer creation succeeded but business application
             * creation failed, remove the newly created customer.
             *
             * This prevents an incomplete customer account from being
             * left behind.
             */
            if ($createdCustomer !== null) {
                $this->removeCreatedCustomer(
                    $createdCustomer
                );
            }

            throw $exception;
        }

        /*
         * Log the new guest customer in only after both the Magento
         * customer and business application were saved successfully.
         */
        if ($createdCustomer !== null) {
            $this->customerSession->setCustomerDataAsLoggedIn(
                $createdCustomer
            );
        }

        return $businessAccount;
    }

    /**
     * Remove leading and trailing whitespace from submitted values.
     */
    private function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $normalized[$key] = trim($value);
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * Validate required application data.
     *
     * @throws LocalizedException
     */
    private function validate(array $data): void
    {
        $requiredFields = [
            'company_name' => __('Company Name'),
            'registration_number' => __(
                'Business Registration Number'
            ),
            'contact_firstname' => __('First Name'),
            'contact_lastname' => __('Last Name'),
            'contact_email' => __('Business Email'),
            'contact_phone' => __('Business Phone'),
            'street' => __('Street Address'),
            'city' => __('City'),
            'postcode' => __('Postcode'),
            'country_id' => __('Country')
        ];

        foreach ($requiredFields as $field => $label) {
            if (
                !isset($data[$field])
                || trim((string)$data[$field]) === ''
            ) {
                throw new LocalizedException(
                    __('The "%1" field is required.', $label)
                );
            }
        }

        if (
            !filter_var(
                $data['contact_email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new LocalizedException(
                __('Please enter a valid business email address.')
            );
        }

        if (
            !isset($data['terms'])
            || (string)$data['terms'] !== '1'
        ) {
            throw new LocalizedException(
                __(
                    'You must confirm that the business information is accurate.'
                )
            );
        }

        if (
            strlen($data['company_name']) > 255
            || strlen($data['contact_email']) > 255
        ) {
            throw new LocalizedException(
                __('One or more submitted values are too long.')
            );
        }

        if (
            strlen($data['registration_number']) > 100
        ) {
            throw new LocalizedException(
                __(
                    'The business registration number cannot exceed 100 characters.'
                )
            );
        }

        if (
            strlen($data['country_id']) !== 2
        ) {
            throw new LocalizedException(
                __('Please select a valid country.')
            );
        }

        if (
            $data['business_years'] !== ''
            && (
                !ctype_digit((string)$data['business_years'])
                || (int)$data['business_years'] < 0
            )
        ) {
            throw new LocalizedException(
                __('Years in Business must be zero or greater.')
            );
        }

        if (!$this->customerSession->isLoggedIn()) {
            $this->validateGuestPassword($data);
        }
    }

    /**
     * Validate password fields for a guest registration.
     *
     * @throws LocalizedException
     */
    private function validateGuestPassword(array $data): void
    {
        $password = (string)($data['password'] ?? '');
        $confirmation = (string)(
            $data['password_confirmation'] ?? ''
        );

        if ($password === '') {
            throw new LocalizedException(
                __('A password is required.')
            );
        }

        if (strlen($password) < 8) {
            throw new LocalizedException(
                __(
                    'The password must contain at least eight characters.'
                )
            );
        }

        if ($password !== $confirmation) {
            throw new LocalizedException(
                __('The password confirmation does not match.')
            );
        }
    }

    /**
     * Ensure that the registration number is not already used.
     *
     * @throws LocalizedException
     */
    private function validateRegistrationNumber(
        string $registrationNumber
    ): void {
        try {
            $this->businessAccountRepository
                ->getByRegistrationNumber(
                    $registrationNumber
                );

            throw new LocalizedException(
                __(
                    'A business account already exists with this registration number.'
                )
            );
        } catch (NoSuchEntityException) {
            /*
             * No existing business account was found.
             * The registration number is available.
             */
        }
    }

    /**
     * Ensure that the logged-in customer has not already applied.
     *
     * @throws LocalizedException
     */
    private function validateCustomerApplication(
        int $customerId
    ): void {
        try {
            $existingApplication = $this
                ->businessAccountRepository
                ->getByCustomerId($customerId);

            throw new LocalizedException(
                __(
                    'You already have a business account application with status "%1".',
                    $existingApplication->getStatus()
                )
            );
        } catch (NoSuchEntityException) {
            /*
             * Customer has no existing application.
             */
        }
    }

    /**
     * Create a Magento customer for a guest applicant.
     *
     * @throws LocalizedException
     */
    private function createCustomer(
        array $data
    ): CustomerInterface {
        $store = $this->storeManager->getStore();
        $websiteId = (int)$store->getWebsiteId();
        $email = $data['contact_email'];

        try {
            $this->customerRepository->get(
                $email,
                $websiteId
            );

            throw new LocalizedException(
                __(
                    'A customer account already exists with this email address. Please sign in before applying for a business account.'
                )
            );
        } catch (NoSuchEntityException) {
            /*
             * No existing customer was found.
             * Continue with account creation.
             */
        }

        $customer = $this->customerFactory->create();

        $customer->setFirstname(
            $data['contact_firstname']
        );

        $customer->setLastname(
            $data['contact_lastname']
        );

        $customer->setEmail($email);

        $customer->setWebsiteId($websiteId);

        $customer->setStoreId(
            (int)$store->getId()
        );

        return $this->accountManagement->createAccount(
            $customer,
            $data['password']
        );
    }

    /**
     * Remove a customer created during a failed registration.
     */
    private function removeCreatedCustomer(
        CustomerInterface $customer
    ): void {
        try {
            if ($customer->getId()) {
                $this->customerRepository->deleteById(
                    (int)$customer->getId()
                );
            }
        } catch (\Throwable) {
            /*
             * Do not hide the original business account save error if
             * customer cleanup also fails.
             */
        }
    }

    /**
     * Combine contact first and last names.
     */
    private function buildContactName(array $data): string
    {
        return trim(
            $data['contact_firstname']
            . ' '
            . $data['contact_lastname']
        );
    }

    /**
     * Convert an empty string into null.
     */
    private function nullableValue(
        mixed $value
    ): ?string {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    /**
     * Convert an optional integer value.
     */
    private function nullableInteger(
        mixed $value
    ): ?int {
        if ($value === '' || $value === null) {
            return null;
        }

        return (int)$value;
    }
}
