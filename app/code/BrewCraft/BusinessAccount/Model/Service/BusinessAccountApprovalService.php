<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Model\Service;

use BrewCraft\BusinessAccount\Api\BusinessAccountRepositoryInterface;
use BrewCraft\BusinessAccount\Model\BusinessAccount;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use BrewCraft\BusinessAccount\Model\Email\BusinessAccountNotifier;

class BusinessAccountApprovalService
{
    private const BUSINESS_CUSTOMER_GROUP_CODE =
        'Business Customer';

    public function __construct(
    private readonly BusinessAccountRepositoryInterface $businessAccountRepository,
    private readonly CustomerRepositoryInterface $customerRepository,
    private readonly GroupCollectionFactory $groupCollectionFactory,
    private readonly DateTime $dateTime,
    private readonly BusinessAccountNotifier $notifier
) {
}

    /**
     * Approve a pending business application.
     *
     * @throws LocalizedException
     */
   public function approve(
    int $entityId,
    ?string $adminComment = null
): BusinessAccount {
    $businessAccount = $this->businessAccountRepository
        ->getById($entityId);

    $this->validatePendingApplication($businessAccount);

    $customerId = (int)$businessAccount->getCustomerId();

    if ($customerId <= 0) {
        throw new LocalizedException(
            __('The business application has no linked customer.')
        );
    }

    $groupId = $this->getBusinessCustomerGroupId();

    $customer = $this->customerRepository->getById(
        $customerId
    );

    $originalGroupId = (int)$customer->getGroupId();

    try {
        $customer->setGroupId($groupId);

        $this->customerRepository->save($customer);

        $businessAccount->setStatus(
            BusinessAccount::STATUS_APPROVED
        );

        $businessAccount->setApprovedAt(
            $this->dateTime->gmtDate()
        );

        $businessAccount->setAdminComment(
            $this->normalizeComment($adminComment)
        );

        $businessAccount = $this
            ->businessAccountRepository
            ->save($businessAccount);
    } catch (\Throwable $exception) {
        /*
         * Attempt to restore the original customer group when
         * approval fails after the customer was updated.
         */
        try {
            $customer->setGroupId($originalGroupId);

            $this->customerRepository->save($customer);
        } catch (\Throwable) {
            /*
             * Preserve the original approval error.
             */
        }

        if ($exception instanceof LocalizedException) {
            throw $exception;
        }

        throw new LocalizedException(
            __('The business application could not be approved.'),
            $exception
        );
    }

    /*
     * Email runs only after the customer and application have been
     * successfully updated.
     *
     * The notifier logs mail failures without failing approval.
     */
    $this->notifier->sendApprovalEmail(
        $businessAccount
    );

    return $businessAccount;
}

    /**
     * Reject a pending business application.
     *
     * @throws LocalizedException
     */
    public function reject(
    int $entityId,
    string $adminComment
): BusinessAccount {
    $adminComment = trim($adminComment);

    if ($adminComment === '') {
        throw new LocalizedException(
            __('A rejection reason is required.')
        );
    }

    $businessAccount = $this->businessAccountRepository
        ->getById($entityId);

    $this->validatePendingApplication($businessAccount);

    $businessAccount->setStatus(
        BusinessAccount::STATUS_REJECTED
    );

    $businessAccount->setApprovedAt(null);

    $businessAccount->setAdminComment(
        $adminComment
    );

    $businessAccount = $this
        ->businessAccountRepository
        ->save($businessAccount);

    $this->notifier->sendRejectionEmail(
        $businessAccount
    );

    return $businessAccount;
}

    /**
     * Ensure only pending applications can be reviewed.
     *
     * @throws LocalizedException
     */
    private function validatePendingApplication(
        BusinessAccount $businessAccount
    ): void {
        if (!$businessAccount->isPending()) {
            throw new LocalizedException(
                __(
                    'This business application has already been reviewed. Current status: %1.',
                    $businessAccount->getStatus()
                )
            );
        }
    }

    /**
     * Find the actual database ID of Business Customer group.
     *
     * @throws LocalizedException
     */
    private function getBusinessCustomerGroupId(): int
    {
        $collection = $this->groupCollectionFactory->create();

        $collection->addFieldToFilter(
            'customer_group_code',
            self::BUSINESS_CUSTOMER_GROUP_CODE
        );

        $collection->setPageSize(1);

        $group = $collection->getFirstItem();

        if (!$group->getId()) {
            throw new LocalizedException(
                __(
                    'The "%1" customer group does not exist. Run setup:upgrade before approving applications.',
                    self::BUSINESS_CUSTOMER_GROUP_CODE
                )
            );
        }

        return (int)$group->getId();
    }

    private function normalizeComment(
        ?string $comment
    ): ?string {
        $comment = trim((string)$comment);

        return $comment !== ''
            ? $comment
            : null;
    }
}