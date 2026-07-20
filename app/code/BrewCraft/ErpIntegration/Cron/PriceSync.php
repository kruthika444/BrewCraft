<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Cron;

use BrewCraft\ErpIntegration\Helper\Config;
use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Service\PriceImportService;
use BrewCraft\ErpIntegration\Model\Service\PriceService;

class PriceSync
{
    public function __construct(
        private readonly PriceService $priceService,
        private readonly PriceImportService $priceImportService,
        private readonly Logger $logger,
        private readonly Config $config
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            $this->logger->info(
                'Price Sync skipped because ERP integration is disabled.'
            );

            return;
        }

        if (!$this->config->isPriceSyncEnabled()) {
            $this->logger->info(
                'Price Sync skipped because price synchronization is disabled.'
            );

            return;
        }

        $this->logger->info(
            'Price Sync Started.'
        );

        try {
            $prices = $this->priceService->getPrices();

            $result = $this->priceImportService->import(
                $prices
            );

            $this->logger->info(
                sprintf(
                    'Price Sync Completed. Updated: %d Failed: %d',
                    (int)($result['updated'] ?? 0),
                    (int)($result['failed'] ?? 0)
                )
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf(
                    'Price Sync Failed: %s',
                    $exception->getMessage()
                )
            );
        }
    }
}
