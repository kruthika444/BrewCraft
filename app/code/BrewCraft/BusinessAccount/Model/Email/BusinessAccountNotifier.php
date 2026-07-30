<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Model\Email;

use BrewCraft\BusinessAccount\Model\BusinessAccount;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class BusinessAccountNotifier
{
    private const APPROVED_TEMPLATE_ID =
        'brewcraft_business_account_approved';

    private const REJECTED_TEMPLATE_ID =
        'brewcraft_business_account_rejected';

    private const GENERAL_EMAIL_NAME_PATH =
        'trans_email/ident_general/name';

    private const GENERAL_EMAIL_ADDRESS_PATH =
        'trans_email/ident_general/email';

    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Send an approval email.
     *
     * Email errors are logged and not rethrown because a mail-delivery
     * failure must not undo an already completed approval operation.
     */
    public function sendApprovalEmail(
        BusinessAccount $businessAccount
    ): void {
        $this->logger->info(
            'BrewCraft approval email process started.',
            [
                'application_id' =>
                    $businessAccount->getEntityId(),

                'customer_id' =>
                    $businessAccount->getCustomerId(),

                'recipient' =>
                    $businessAccount->getContactEmail()
            ]
        );

        try {
            $storeId = $this->resolveStoreId(
                $businessAccount
            );

            $this->send(
                self::APPROVED_TEMPLATE_ID,
                $businessAccount,
                $storeId,
                [
                    'admin_comment' => (string)(
                        $businessAccount->getAdminComment() ?? ''
                    )
                ]
            );

            $this->logger->info(
                'BrewCraft approval email sent successfully.',
                [
                    'application_id' =>
                        $businessAccount->getEntityId(),

                    'recipient' =>
                        $businessAccount->getContactEmail()
                ]
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                'BrewCraft approval email failed.',
                [
                    'application_id' =>
                        $businessAccount->getEntityId(),

                    'customer_id' =>
                        $businessAccount->getCustomerId(),

                    'recipient' =>
                        $businessAccount->getContactEmail(),

                    'error' =>
                        $exception->getMessage(),

                    'exception' =>
                        $exception
                ]
            );
        }
    }

    /**
     * Send a rejection email.
     *
     * Email errors are logged and not rethrown because a mail-delivery
     * failure must not undo an already completed rejection operation.
     */
    public function sendRejectionEmail(
        BusinessAccount $businessAccount
    ): void {
        $this->logger->info(
            'BrewCraft rejection email process started.',
            [
                'application_id' =>
                    $businessAccount->getEntityId(),

                'customer_id' =>
                    $businessAccount->getCustomerId(),

                'recipient' =>
                    $businessAccount->getContactEmail()
            ]
        );

        try {
            $storeId = $this->resolveStoreId(
                $businessAccount
            );

            $this->send(
                self::REJECTED_TEMPLATE_ID,
                $businessAccount,
                $storeId,
                [
                    'rejection_reason' => (string)(
                        $businessAccount->getAdminComment() ?? ''
                    )
                ]
            );

            $this->logger->info(
                'BrewCraft rejection email sent successfully.',
                [
                    'application_id' =>
                        $businessAccount->getEntityId(),

                    'recipient' =>
                        $businessAccount->getContactEmail()
                ]
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                'BrewCraft rejection email failed.',
                [
                    'application_id' =>
                        $businessAccount->getEntityId(),

                    'customer_id' =>
                        $businessAccount->getCustomerId(),

                    'recipient' =>
                        $businessAccount->getContactEmail(),

                    'error' =>
                        $exception->getMessage(),

                    'exception' =>
                        $exception
                ]
            );
        }
    }

    /**
     * Build and send the transactional email.
     *
     * @throws LocalizedException
     */
    private function send(
        string $templateId,
        BusinessAccount $businessAccount,
        int $storeId,
        array $additionalVariables = []
    ): void {
        $customerId = (int)$businessAccount->getCustomerId();

        if ($customerId <= 0) {
            throw new LocalizedException(
                __('The business application has no linked customer.')
            );
        }

        $customer = $this->customerRepository->getById(
            $customerId
        );

        $recipientEmail = trim(
            (string)$businessAccount->getContactEmail()
        );

        if ($recipientEmail === '') {
            $recipientEmail = trim(
                (string)$customer->getEmail()
            );
        }

        if ($recipientEmail === '') {
            throw new LocalizedException(
                __('The business applicant has no email address.')
            );
        }

        $customerName = trim(
            (string)$customer->getFirstname()
            . ' '
            . (string)$customer->getLastname()
        );

        if ($customerName === '') {
            $customerName = trim(
                (string)$businessAccount->getContactName()
            );
        }

        if ($customerName === '') {
            $customerName = (string)__(
                'BrewCraft Customer'
            );
        }

        $store = $this->storeManager->getStore(
            $storeId
        );

        $templateVariables = array_merge(
            [
                'customer_name' => $customerName,

                'company_name' => (string)$businessAccount
                    ->getCompanyName(),

                'account_url' => $store->getUrl(
                    'customer/account'
                ),

                'store_name' => (string)$store->getName()
            ],
            $additionalVariables
        );

        $senderName = trim(
            (string)$this->scopeConfig->getValue(
                self::GENERAL_EMAIL_NAME_PATH,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
        );

        $senderEmail = trim(
            (string)$this->scopeConfig->getValue(
                self::GENERAL_EMAIL_ADDRESS_PATH,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
        );

        if ($senderEmail === '') {
            throw new LocalizedException(
                __(
                    'The store General Contact email is not configured.'
                )
            );
        }

        if ($senderName === '') {
            $senderName = (string)$store->getName();
        }

        $sender = [
            'name' => $senderName,
            'email' => $senderEmail
        ];

        $transport = $this->transportBuilder
            ->setTemplateIdentifier($templateId)
            ->setTemplateOptions([
                'area' => Area::AREA_FRONTEND,
                'store' => $storeId
            ])
            ->setTemplateVars($templateVariables)
            ->setFrom($sender)
            ->addTo(
                $recipientEmail,
                $customerName
            )
            ->getTransport();

        $transport->sendMessage();
    }

    /**
     * Resolve the correct store view for email rendering.
     */
    private function resolveStoreId(
        BusinessAccount $businessAccount
    ): int {
        $customerId = (int)$businessAccount->getCustomerId();

        if ($customerId > 0) {
            $customer = $this->customerRepository->getById(
                $customerId
            );

            $storeId = (int)$customer->getStoreId();

            if ($storeId > 0) {
                return $storeId;
            }
        }

        $defaultStore = $this->storeManager
            ->getDefaultStoreView();

        if ($defaultStore === null) {
            throw new LocalizedException(
                __('No default store view is available.')
            );
        }

        return (int)$defaultStore->getId();
    }
}