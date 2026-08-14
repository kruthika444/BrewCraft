<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Media\ProductImageImporter;
use BrewCraft\ErpIntegration\Model\Resolver\CategoryResolver;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Exception\NoSuchEntityException;

class ProductImportService
{
    private const ATTRIBUTE_SET_ID = 4;

    private const TYPE_SIMPLE = 'simple';

    private const VISIBILITY_CATALOG_SEARCH = 4;

    private const STATUS_ENABLED = 1;

    private const STATUS_DISABLED = 2;

    public function __construct(
        private readonly ProductFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryResolver $categoryResolver,
        private readonly ProductImageImporter $productImageImporter,
        private readonly Logger $logger
    ) {
    }

    public function import(array $products): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0
        ];

        foreach ($products as $erpProduct) {
            try {
                $isNew = false;

                try {
                    /*
                     * Explicitly load Admin/default scope.
                     *
                     * This is important for product images because
                     * we do not want the ERP importer accidentally
                     * creating storefront-specific image values.
                     */
                    $product = $this->productRepository->get(
                        (string)$erpProduct['sku'],
                        false,
                        0,
                        true
                    );
                } catch (NoSuchEntityException) {
                    $product = $this->productFactory
                        ->create();

                    $product->setStoreId(0);

                    $product->setSku(
                        (string)$erpProduct['sku']
                    );

                    $product->setTypeId(
                        self::TYPE_SIMPLE
                    );

                    $product->setAttributeSetId(
                        self::ATTRIBUTE_SET_ID
                    );

                    $isNew = true;
                }

                /*
                 * Always synchronize ERP-owned product data
                 * at Admin/default scope.
                 */
                $product->setStoreId(0);

                $this->mapProduct(
                    $product,
                    $erpProduct
                );

                /*
                 * Save core product data first.
                 *
                 * New products need a Magento entity ID before
                 * media-gallery processing.
                 */
                $product = $this->productRepository
                    ->save($product);

                /*
                 * Product image failure must not fail the
                 * complete product synchronization.
                 *
                 * ProductImageImporter handles/logs media errors.
                 */
                $mediaChanged = $this->productImageImporter
                    ->import(
                        $product,
                        $erpProduct
                    );

                if ($mediaChanged) {
                    $this->productRepository
                        ->save($product);
                }

                if ($isNew) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }

                $this->logger->info(
                    sprintf(
                        'Product "%s" synchronized.',
                        $erpProduct['sku']
                    )
                );
            } catch (\Throwable $exception) {
                $result['failed']++;

                $this->logger->error(
                    sprintf(
                        'Failed importing %s : %s',
                        $erpProduct['sku'] ?? 'UNKNOWN',
                        $exception->getMessage()
                    )
                );
            }
        }

        return $result;
    }

    private function mapProduct(
        Product $product,
        array $erpProduct
    ): void {
        $product->setName(
            (string)$erpProduct['name']
        );

        $product->setPrice(
            (float)$erpProduct['price']
        );

        $product->setWeight(
            (float)$erpProduct['weight']
        );

        $product->setVisibility(
            self::VISIBILITY_CATALOG_SEARCH
        );

        $product->setStatus(
            ($erpProduct['status'] ?? '') === 'ACTIVE'
                ? self::STATUS_ENABLED
                : self::STATUS_DISABLED
        );

        $category = $this->categoryResolver
            ->getByErpCode(
                (string)$erpProduct['category_code']
            );

        if (!$category) {
            throw new \RuntimeException(
                sprintf(
                    'Category "%s" not found.',
                    $erpProduct['category_code']
                )
            );
        }

        $product->setCategoryIds(
            [
                (int)$category->getId()
            ]
        );

        /*
         * Optional ERP Attributes
         */

        $product->setData(
            'manufacturer',
            $erpProduct['manufacturer'] ?? null
        );

        $product->setData(
            'barcode',
            $erpProduct['barcode'] ?? null
        );

        $product->setData(
            'country_of_origin',
            $erpProduct['country_of_origin'] ?? null
        );

        $product->setData(
            'cost_price',
            $erpProduct['cost_price'] ?? null
        );
    }
}
