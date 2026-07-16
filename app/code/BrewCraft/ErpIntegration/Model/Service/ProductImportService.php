<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Logger\Logger;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use BrewCraft\ErpIntegration\Model\Service\CategoryService;

class ProductImportService
{
    private const DEFAULT_ATTRIBUTE_SET_ID = 4;
    private const VISIBILITY_CATALOG_SEARCH = 4;
    private const STATUS_ENABLED = 1;
    private const STATUS_DISABLED = 2;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductFactory $productFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger $logger,
        private readonly CategoryService $categoryService
    ) {}

    public function import(array $products): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0
        ];

        foreach ($products as $erpProduct) {
            try {
                $isNew = $this->importProduct($erpProduct);

                if ($isNew) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }
            } catch (\Throwable $exception) {
                $result['failed']++;

                $this->logger->error(sprintf(
                    'Failed importing %s : %s',
                    $erpProduct['sku'] ?? 'UNKNOWN',
                    $exception->getMessage()
                ));
            }
        }

        return $result;
    }
    private function importProduct(array $erpProduct): bool
    {
        $isNew = false;

        try {
            $product = $this->getExistingProduct($erpProduct['sku']);
        } catch (NoSuchEntityException $exception) {
            $isNew = true;
            $product = $this->productFactory->create();
            $product->setSku($erpProduct['sku']);
            $product->setTypeId('simple');
            $product->setAttributeSetId(self::DEFAULT_ATTRIBUTE_SET_ID);
            $product->setWebsiteIds([
                $this->storeManager
                    ->getStore()
                    ->getWebsiteId()
            ]);
            $product->setTaxClassId(2);

            $product->setStockData([
                'qty' => 100,
                'is_in_stock' => 1
            ]);
        }

        $this->mapProduct($product, $erpProduct);

        try {
            $this->productRepository->save($product);

            $this->logger->info(
                sprintf(
                    'Product %s imported successfully.',
                    $erpProduct['sku']
                )
            );
        } catch (\Throwable $exception) {

            $this->logger->error(
                sprintf(
                    'Failed importing %s : %s',
                    $erpProduct['sku'],
                    $exception->getMessage()
                )
            );
        }
        return $isNew;
    }

    private function mapProduct(
        ProductInterface $product,
        array $erpProduct
    ): void {

        $product->setName($erpProduct['name']);
        $product->setPrice($erpProduct['price']);
        $product->setWeight($erpProduct['weight']);

        $product->setVisibility(self::VISIBILITY_CATALOG_SEARCH);

        $product->setStatus(
            $erpProduct['status'] === 'ACTIVE'
                ? self::STATUS_ENABLED
                : self::STATUS_DISABLED
        );
        $categoryId = $this->categoryService->getCategoryId(
            $erpProduct['category_code']
        );
        $product->setCategoryIds([$categoryId]);
    }

    private function getExistingProduct(string $sku): ProductInterface
    {
        return $this->productRepository->get($sku);
    }
}
