<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Cron;

use BrewCraft\ErpIntegration\Helper\Config;
use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Service\CategoryImportService;
use BrewCraft\ErpIntegration\Model\Service\ProductImportService;
use BrewCraft\ErpIntegration\Model\Service\ProductService;

class ProductSync
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductImportService $productImportService,
        private readonly CategoryImportService $categoryImportService,
        private readonly Logger $logger,
        private readonly Config $config
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            $this->logger->info(
                'Category and Product Sync skipped because ERP integration is disabled.'
            );

            return;
        }

        if (!$this->config->isProductSyncEnabled()) {
            $this->logger->info(
                'Category and Product Sync skipped because product synchronization is disabled.'
            );

            return;
        }

        $this->logger->info(
            'Category and Product Sync Started.'
        );

        try {
            $this->logger->info(
                'Importing Categories...'
            );

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
