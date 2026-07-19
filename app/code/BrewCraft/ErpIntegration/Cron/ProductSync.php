<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Cron;

use BrewCraft\ErpIntegration\Model\Service\ProductService;
use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Service\CategoryImportService;
use BrewCraft\ErpIntegration\Model\Service\ProductImportService;

class ProductSync
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly Logger $logger,
        private readonly ProductImportService $productImportService,
        private readonly CategoryImportService $categoryImportService
    ) {}

    public function execute(): void
    {
        $this->logger->info('Product Sync Started');

        try {

            $this->logger->info('Importing Categories...');
            $this->categoryImportService->import();

            $this->logger->info('Categories Imported.');

            $products = $this->productService->getProducts();
            $this->productImportService->import($products);

            $this->logger->info('Products Imported.');
        } catch (\Throwable $exception) {

            $this->logger->critical($exception);
        }

        $this->logger->info('Product Sync Finished');
    }
}
