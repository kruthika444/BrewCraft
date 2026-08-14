<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Resolver\CategoryResolver;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use BrewCraft\ErpIntegration\Model\Media\CategoryImageImporter;

class CategoryImportService
{
    /**
     * ERP category code => Magento category ID.
     *
     * @var array<string, int>
     */
    private array $categoryMap = [];

    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly CategoryResolver $categoryResolver,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryImageImporter $categoryImageImporter,
        private readonly Logger $logger
    ) {}

    /**
     * Import ERP categories into Magento.
     */
    public function import(): void
    {
        $categories = $this->categoryService->getCategories();

        if (empty($categories)) {
            $this->logger->info(
                'No categories received from ERP.'
            );

            return;
        }

        /*
         * Reset map for every import execution.
         */
        $this->categoryMap = [];

        /*
         * Categories are processed in passes.
         *
         * A category is imported only when:
         *
         * 1. It has no ERP parent, or
         * 2. Its ERP parent has already been imported/resolved.
         *
         * This makes the importer independent of the order
         * in which ERP returns categories.
         */
        $pendingCategories = $categories;

        while (!empty($pendingCategories)) {
            $processedInCurrentPass = 0;

            foreach ($pendingCategories as $key => $erpCategory) {
                $this->validateCategoryData($erpCategory);

                $parentCode = $erpCategory['parent_code'] ?? null;

                /*
                 * If the category has a parent but the parent has not
                 * yet been resolved, skip it for the current pass.
                 */
                if (
                    $parentCode !== null
                    && !$this->canResolveParent($parentCode)
                ) {
                    continue;
                }

                $this->importCategory($erpCategory);

                unset($pendingCategories[$key]);

                $processedInCurrentPass++;
            }

            /*
             * If an entire pass could not process even one category,
             * the ERP hierarchy contains a missing parent or circular
             * parent relationship.
             */
            if ($processedInCurrentPass === 0) {
                $unresolved = [];

                foreach ($pendingCategories as $category) {
                    $unresolved[] = sprintf(
                        '%s(parent=%s)',
                        $category['code'] ?? 'UNKNOWN',
                        $category['parent_code'] ?? 'NULL'
                    );
                }

                throw new LocalizedException(
                    __(
                        'Unable to resolve ERP category hierarchy. Unresolved categories: %1',
                        implode(', ', $unresolved)
                    )
                );
            }

            /*
             * Re-index array after unset().
             */
            $pendingCategories = array_values(
                $pendingCategories
            );
        }

        $this->logger->info(
            sprintf(
                'Category Sync Completed. Imported/Synchronized: %d',
                count($this->categoryMap)
            )
        );
    }

    /**
     * Import one ERP category.
     */
    private function importCategory(array $erpCategory): void
    {
        $code = (string)$erpCategory['code'];

        $parentCode = $erpCategory['parent_code'] ?? null;

        $parentId = $this->resolveParentId(
            $parentCode
        );

        $category = $this->categoryResolver
            ->getByErpCode($code);

        if (!$category) {
            $category = $this->categoryResolver->create();

            /*
             * Store ID 0 means save category attributes
             * at Admin / default scope.
             *
             * This is fine.
             *
             * It is NOT the same thing as parent_id = 0.
             */
            $category->setStoreId(0);
        }

        /*
         * Set parent for both new and existing categories.
         *
         * This allows ERP hierarchy changes to move an
         * existing Magento category to another parent.
         */
        $category->setParentId($parentId);

        $this->mapCategory(
            $category,
            $erpCategory
        );

        $savedCategory = $this->categoryRepository->save(
            $category
        );

        $categoryImageChanged = $this->categoryImageImporter
            ->import(
                $savedCategory,
                $erpCategory
            );

        if ($categoryImageChanged) {
            $savedCategory = $this->categoryRepository
                ->save($savedCategory);
        }

        $categoryId = (int)$savedCategory->getId();

        if ($categoryId <= 0) {
            throw new LocalizedException(
                __(
                    'Magento category ID was not generated for ERP category "%1".',
                    $code
                )
            );
        }

        $this->categoryMap[$code] = $categoryId;

        $this->logger->info(
            sprintf(
                'Category "%s" [%s] synchronized. Magento ID: %d, Parent ID: %d.',
                $erpCategory['name'],
                $code,
                $categoryId,
                $parentId
            )
        );
    }

    /**
     * Determine whether an ERP parent can currently be resolved.
     */
    private function canResolveParent(string $parentCode): bool
    {
        if (isset($this->categoryMap[$parentCode])) {
            return true;
        }

        $parent = $this->categoryResolver
            ->getByErpCode($parentCode);

        if (!$parent) {
            return false;
        }

        $parentId = (int)$parent->getId();

        if ($parentId <= 0) {
            return false;
        }

        $this->categoryMap[$parentCode] = $parentId;

        return true;
    }

    /**
     * Resolve Magento parent category ID.
     */
    private function resolveParentId(?string $parentCode): int
    {
        /*
         * ERP top-level category.
         *
         * IMPORTANT:
         * Do not use:
         *
         * $this->storeManager->getStore()
         *
         * here.
         *
         * During cron/CLI execution Magento may be in the
         * Admin store context (store ID 0), whose root category
         * is not the storefront catalog root.
         */
        if ($parentCode === null || $parentCode === '') {
            $defaultStore = $this->storeManager
                ->getDefaultStoreView();

            if ($defaultStore === null) {
                throw new LocalizedException(
                    __('Unable to resolve the default Magento store view.')
                );
            }

            $rootCategoryId = (int)$defaultStore
                ->getRootCategoryId();

            if ($rootCategoryId <= 0) {
                throw new LocalizedException(
                    __(
                        'Invalid Magento root category ID "%1".',
                        $rootCategoryId
                    )
                );
            }

            return $rootCategoryId;
        }

        if (isset($this->categoryMap[$parentCode])) {
            return $this->categoryMap[$parentCode];
        }

        $parent = $this->categoryResolver
            ->getByErpCode($parentCode);

        if (!$parent) {
            throw new LocalizedException(
                __(
                    'Parent ERP category "%1" was not found in Magento.',
                    $parentCode
                )
            );
        }

        $parentId = (int)$parent->getId();

        if ($parentId <= 0) {
            throw new LocalizedException(
                __(
                    'Invalid Magento category ID for ERP parent "%1".',
                    $parentCode
                )
            );
        }

        $this->categoryMap[$parentCode] = $parentId;

        return $parentId;
    }

    /**
     * Copy ERP fields into Magento category.
     */
    private function mapCategory(
        Category $category,
        array $erpCategory
    ): void {
        $category->setName(
            (string)$erpCategory['name']
        );

        $category->setData(
            'erp_category_code',
            (string)$erpCategory['code']
        );

        $category->setIsActive(
            ($erpCategory['status'] ?? '') === 'ACTIVE'
        );

        $category->setUrlKey(
            strtolower(
                str_replace(
                    ' ',
                    '-',
                    trim((string)$erpCategory['name'])
                )
            )
        );

        /*
     * Only overwrite the Magento description when ERP
     * explicitly sends the field.
     *
     * This prevents old ERP payloads without "description"
     * from accidentally clearing existing Magento content.
     */
        if (
            array_key_exists(
                'description',
                $erpCategory
            )
        ) {
            $category->setDescription(
                (string)$erpCategory['description']
            );
        }
    }
    /**
     * Validate required ERP fields.
     */
    private function validateCategoryData(array $erpCategory): void
    {
        if (
            empty($erpCategory['code'])
            || empty($erpCategory['name'])
        ) {
            throw new LocalizedException(
                __(
                    'Invalid ERP category data. Both code and name are required.'
                )
            );
        }
    }
}
