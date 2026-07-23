<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Api;

use BrewCraft\BusinessAccount\Model\BusinessAccount;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface BusinessAccountRepositoryInterface
{
    /**
     * Save a business account application.
     *
     * @throws CouldNotSaveException
     */
    public function save(
        BusinessAccount $businessAccount
    ): BusinessAccount;

    /**
     * Get a business account application by entity ID.
     *
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): BusinessAccount;

    /**
     * Get a business account application by Magento customer ID.
     *
     * @throws NoSuchEntityException
     */
    public function getByCustomerId(int $customerId): BusinessAccount;

    /**
     * Get a business account application by registration number.
     *
     * @throws NoSuchEntityException
     */
    public function getByRegistrationNumber(
        string $registrationNumber
    ): BusinessAccount;

    /**
     * Delete a business account application.
     *
     * @throws CouldNotDeleteException
     */
    public function delete(
        BusinessAccount $businessAccount
    ): bool;

    /**
     * Delete a business account application by entity ID.
     *
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool;
}
