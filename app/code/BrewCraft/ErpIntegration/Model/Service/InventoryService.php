<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Api\JobRepositoryInterface;
use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Api\Client;
use BrewCraft\ErpIntegration\Model\JobFactory;

class InventoryService
{
    public function __construct(
        private readonly Client $client,
        private readonly Logger $logger,
        private readonly JobRepositoryInterface $jobRepository,
        private readonly JobFactory $jobFactory
    ) {
    }

    /**
     * Fetch inventory from ERP.
     */
    public function getInventory(): array
    {
        $response = $this->client->getInventory();

        $inventory = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Invalid ERP JSON Response : '
                . json_last_error_msg()
            );
        }

        if (!is_array($inventory)) {
            throw new \RuntimeException(
                'ERP response is not a valid inventory array.'
            );
        }

        $this->logger->info(
            sprintf(
                'Fetched %d inventory records.',
                count($inventory)
            )
        );

        foreach ($inventory as $item) {

            foreach (
                [
                    'sku',
                    'qty'
                ] as $field
            ) {

                if (!array_key_exists($field, $item)) {

                    throw new \RuntimeException(
                        sprintf(
                            'Missing "%s" in inventory payload.',
                            $field
                        )
                    );
                }
            }
        }

        $job = $this->jobFactory->create();

        $job->setData([
            'job_type' => 'INVENTORY_SYNC',
            'status' => 'SUCCESS',
            'records_processed' => count($inventory),
            'execution_time' => 0,
            'message' => 'Inventory fetched successfully.'
        ]);

        $this->jobRepository->save($job);

        return $inventory;
    }
}
