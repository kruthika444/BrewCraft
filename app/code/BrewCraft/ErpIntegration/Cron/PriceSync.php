<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Cron;

use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Service\PriceImportService;
use BrewCraft\ErpIntegration\Model\Service\PriceService;

class PriceSync
{
    public function __construct(
        private readonly PriceService $priceService,
        private readonly PriceImportService $priceImportService,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $prices = $this->priceService->getPrices();

            $result = $this->priceImportService->import($prices);

            $this->logger->info(
                sprintf(
                    'Price Sync Completed. Updated: %d Failed: %d',
                    $result['updated'],
                    $result['failed']
                )
            );

        } catch (\Throwable $exception) {

            $this->logger->error(
                'Price Sync Failed : '
                . $exception->getMessage()
            );
        }
    }
}
