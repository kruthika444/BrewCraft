<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Cron;

use BrewCraft\ErpIntegration\Helper\Config;
use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Service\InventoryImportService;
use BrewCraft\ErpIntegration\Model\Service\InventoryService;

class InventorySync
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryImportService $inventoryImportService,
        private readonly Logger $logger,
        private readonly Config $config
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            $this->logger->info(
                'Inventory Sync skipped because ERP integration is disabled.'
            );

            return;
        }

        if (!$this->config->isInventorySyncEnabled()) {
            $this->logger->info(
                'Inventory Sync skipped because inventory synchronization is disabled.'
            );

            return;
        }

        $this->logger->info(
            'Inventory Sync Started.'
        );

        try {
            $inventory = $this->inventoryService->getInventory();

            $result = $this->inventoryImportService->import(
                $inventory
            );

            $this->logger->info(
                sprintf(
                    'Inventory Sync Completed. Updated: %d Failed: %d',
                    (int)($result['updated'] ?? 0),
                    (int)($result['failed'] ?? 0)
                )
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf(
                    'Inventory Sync Failed: %s',
                    $exception->getMessage()
                )
            );
        }
    }
}
