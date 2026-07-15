<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Cron;

use BrewCraft\ErpIntegration\Model\Service\ProductService;
use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Service\ProductImportService;


class ProductSync
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly Logger $logger,
        private readonly ProductImportService $productImportService
    ) {}

    public function execute(): void
    {
        $this->logger->info('Product Sync Started');

        try {

            $products = $this->productService->getProducts();
            $this->productImportService->import($products);
        } catch (\Throwable $exception) {

            $this->logger->critical($exception);
        }

        $this->logger->info('Product Sync Finished');
    }
}
