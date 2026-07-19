<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Resolver\CategoryResolver;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class CategoryImportService
{
    /**
     * ERP Code => Magento Category ID
     */
    private array $categoryMap = [];

    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly CategoryResolver $categoryResolver,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger $logger
    ) {
    }

    public function import(): void
    {
        $categories = $this->categoryService->getCategories();

        /**
         * Sort so parent categories are imported first.
         */
        usort(
            $categories,
            function (array $a, array $b): int {

                if ($a['parent_code'] === null && $b['parent_code'] !== null) {
                    return -1;
                }

                if ($a['parent_code'] !== null && $b['parent_code'] === null) {
                    return 1;
                }

                return 0;
            }
        );

        foreach ($categories as $erpCategory) {

            $parentId = $this->resolveParentId(
                $erpCategory['parent_code']
            );

            $category = $this->categoryResolver
                ->getByErpCode(
                    $erpCategory['code']
                );

            if (!$category) {

                $category = $this->categoryResolver->create();

                $category->setStoreId(0);
                $category->setParentId($parentId);
            }

            $this->mapCategory(
                $category,
                $erpCategory
            );

            $this->categoryRepository->save(
                $category
            );

            $this->categoryMap[
                $erpCategory['code']
            ] = (int)$category->getId();

            $this->logger->info(
                sprintf(
                    'Category "%s" synchronized.',
                    $erpCategory['name']
                )
            );
        }
    }

    /**
     * Resolve Magento parent category ID.
     */
    private function resolveParentId(
        ?string $parentCode
    ): int {

        if ($parentCode === null) {

            return (int)$this->storeManager
                ->getStore()
                ->getRootCategoryId();
        }

        if (!isset($this->categoryMap[$parentCode])) {

            $parent = $this->categoryResolver
                ->getByErpCode(
                    $parentCode
                );

            if (!$parent) {

                throw new \RuntimeException(
                    sprintf(
                        'Parent category "%s" not found.',
                        $parentCode
                    )
                );
            }

            $this->categoryMap[$parentCode]
                = (int)$parent->getId();
        }

        return $this->categoryMap[$parentCode];
    }

    /**
     * Copy ERP fields into Magento category.
     */
    private function mapCategory(
        Category $category,
        array $erpCategory
    ): void {

        $category->setName(
            $erpCategory['name']
        );

        $category->setData(
            'erp_category_code',
            $erpCategory['code']
        );

        $category->setIsActive(
            $erpCategory['status'] === 'ACTIVE'
        );

        $category->setUrlKey(
            strtolower(
                str_replace(
                    ' ',
                    '-',
                    $erpCategory['name']
                )
            )
        );
    }
}
