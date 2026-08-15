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

        /*
     * ========================================================
     * REQUIRED ERP DATA
     * ========================================================
     */

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


        /*
     * ========================================================
     * CATEGORY
     * ========================================================
     */

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

        $product->setCategoryIds([
            (int)$category->getId()
        ]);


        /*
     * ========================================================
     * ERP PRODUCT CONTENT
     *
     * These attributes already exist natively in Magento.
     *
     * IMPORTANT:
     *
     * Only update when ERP actually sends the field.
     *
     * Missing ERP field:
     * → Magento value remains untouched.
     * ========================================================
     */

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'short_description'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'description'
        );


        /*
     * ========================================================
     * EXISTING ERP ATTRIBUTES
     * ========================================================
     */

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'manufacturer'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'barcode'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'country_of_origin'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'cost_price'
        );


        /*
     * ========================================================
     * COFFEE SPECIFICATIONS
     * ========================================================
     */

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'bean_type'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'roast_level'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'flavor_profile'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'brew_methods'
        );


        /*
     * ========================================================
     * EQUIPMENT SPECIFICATIONS
     * ========================================================
     */

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'capacity'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'material'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'power'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'voltage'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'warranty'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'grinder_type'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'burr_type'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'water_tank_capacity'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'bean_hopper_capacity'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'pump_pressure'
        );

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'dimensions'
        );


        /*
     * ========================================================
     * WHAT'S INCLUDED
     * ========================================================
     */

        $this->mapOptionalAttribute(
            $product,
            $erpProduct,
            'included_items'
        );
    }

    /**
     * Safely map an optional ERP field.
     *
     * Rules:
     *
     * 1. Field missing completely
     *    → do nothing
     *
     * 2. Field exists but value is null
     *    → do nothing
     *
     * 3. ERP sends a scalar value
     *    → save it
     *
     * 4. ERP sends an array
     *    → convert it into a readable comma-separated value
     *
     * This prevents optional ERP data from breaking
     * product synchronization.
     */
    private function mapOptionalAttribute(
        Product $product,
        array $erpProduct,
        string $attributeCode
    ): void {

        /*
     * ERP did not send this attribute.
     *
     * Preserve whatever Magento currently has.
     */
        if (!array_key_exists(
            $attributeCode,
            $erpProduct
        )) {
            return;
        }

        $value = $erpProduct[$attributeCode];

        /*
     * Null means ERP did not provide a usable value.
     */
        if ($value === null) {
            return;
        }


        /*
     * Arrays are useful in ERP JSON for fields such as:
     *
     * brew_methods
     * included_items
     *
     * Convert them into a Magento-friendly string.
     */
        if (is_array($value)) {

            $value = array_filter(
                array_map(
                    static fn($item): string =>
                    trim((string)$item),
                    $value
                ),
                static fn(string $item): bool =>
                $item !== ''
            );

            /*
         * ERP array was empty.
         */
            if (empty($value)) {
                return;
            }

            $value = implode(
                ', ',
                $value
            );
        }


        /*
     * Protect against unsupported complex values.
     */
        if (
            !is_string($value)
            && !is_int($value)
            && !is_float($value)
            && !is_bool($value)
        ) {
            $this->logger->warning(
                sprintf(
                    'ERP attribute "%s" for product "%s" contains an unsupported value and was skipped.',
                    $attributeCode,
                    $product->getSku()
                )
            );

            return;
        }


        $product->setData(
            $attributeCode,
            $value
        );
    }
}
