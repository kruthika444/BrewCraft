<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Cron;

use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Service\InventoryImportService;
use BrewCraft\ErpIntegration\Model\Service\InventoryService;

class InventorySync
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryImportService $inventoryImportService,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $inventory = $this->inventoryService->getInventory();

            $result = $this->inventoryImportService->import($inventory);

            $this->logger->info(
                sprintf(
                    'Inventory Sync Completed. Updated: %d Failed: %d',
                    $result['updated'],
                    $result['failed']
                )
            );

        } catch (\Throwable $exception) {

            $this->logger->error(
                'Inventory Sync Failed : '
                . $exception->getMessage()
            );
        }
    }
}
