<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Model;

use BrewCraft\BusinessAccount\Api\BusinessAccountRepositoryInterface;
use BrewCraft\BusinessAccount\Model\ResourceModel\BusinessAccount as BusinessAccountResource;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class BusinessAccountRepository implements BusinessAccountRepositoryInterface
{
    public function __construct(
        private readonly BusinessAccountResource $resource,
        private readonly BusinessAccountFactory $businessAccountFactory
    ) {
    }

    /**
     * Save a business account application.
     */
    public function save(
        BusinessAccount $businessAccount
    ): BusinessAccount {
        try {
            $this->resource->save($businessAccount);
        } catch (\Throwable $exception) {
            throw new CouldNotSaveException(
                __(
                    'The business account application could not be saved. Error: %1',
                    $exception->getMessage()
                ),
                $exception
            );
        }

        return $businessAccount;
    }

    /**
     * Get a business account application by entity ID.
     */
    public function getById(int $entityId): BusinessAccount
    {
        $businessAccount = $this->businessAccountFactory->create();

        $this->resource->load(
            $businessAccount,
            $entityId
        );

        if (!$businessAccount->getEntityId()) {
            throw new NoSuchEntityException(
                __(
                    'The business account application with ID "%1" does not exist.',
                    $entityId
                )
            );
        }

        return $businessAccount;
    }

    /**
     * Get a business account application by Magento customer ID.
     */
    public function getByCustomerId(
        int $customerId
    ): BusinessAccount {
        $businessAccount = $this->businessAccountFactory->create();

        $this->resource->load(
            $businessAccount,
            $customerId,
            'customer_id'
        );

        if (!$businessAccount->getEntityId()) {
            throw new NoSuchEntityException(
                __(
                    'No business account application exists for customer ID "%1".',
                    $customerId
                )
            );
        }

        return $businessAccount;
    }

    /**
     * Get a business account application by registration number.
     */
    public function getByRegistrationNumber(
        string $registrationNumber
    ): BusinessAccount {
        $businessAccount = $this->businessAccountFactory->create();

        $this->resource->load(
            $businessAccount,
            $registrationNumber,
            'registration_number'
        );

        if (!$businessAccount->getEntityId()) {
            throw new NoSuchEntityException(
                __(
                    'No business account application exists with registration number "%1".',
                    $registrationNumber
                )
            );
        }

        return $businessAccount;
    }

    /**
     * Delete a business account application.
     */
    public function delete(
        BusinessAccount $businessAccount
    ): bool {
        try {
            $this->resource->delete($businessAccount);
        } catch (\Throwable $exception) {
            throw new CouldNotDeleteException(
                __(
                    'The business account application could not be deleted. Error: %1',
                    $exception->getMessage()
                ),
                $exception
            );
        }

        return true;
    }

    /**
     * Delete a business account application by entity ID.
     */
    public function deleteById(int $entityId): bool
    {
        $businessAccount = $this->getById($entityId);

        return $this->delete($businessAccount);
    }
}
