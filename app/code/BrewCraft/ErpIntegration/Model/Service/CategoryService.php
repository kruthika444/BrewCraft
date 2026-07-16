<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Logger\Logger;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class CategoryService
{
    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory,
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger $logger
    ) {}

    public function getCategoryId(string $erpCategoryCode): int
    {
        $category = $this->findCategory($erpCategoryCode);

        if ($category) {
            return (int)$category->getId();
        }

        return $this->createCategory($erpCategoryCode);
    }

    private function findCategory(string $erpCategoryCode)
    {
        $categoryName = ucwords(
            strtolower(
                str_replace('_', ' ', $erpCategoryCode)
            )
        );

        $collection = $this->categoryCollectionFactory->create();

        $collection->addAttributeToSelect('name');

        $collection->addAttributeToFilter(
            'name',
            $categoryName
        );

        return $collection->getFirstItem()->getId()
            ? $collection->getFirstItem()
            : null;
    }

    private function createCategory(string $erpCategoryCode): int
    {
        $categoryName = ucwords(
            strtolower(
                str_replace('_', ' ', $erpCategoryCode)
            )
        );

        $category = $this->categoryFactory->create();

        $rootCategoryId = $this->storeManager
            ->getStore()
            ->getRootCategoryId();

        $category->setParentId($rootCategoryId);

        $category->setName($categoryName);

        $category->setIsActive(true);

        $category->setPath("1/{$rootCategoryId}");

        $category->setUrlKey(
            strtolower(
                str_replace(' ', '-', $categoryName)
            )
        );

        $this->categoryRepository->save($category);

        $this->logger->info(
            sprintf(
                'Created category %s',
                $categoryName
            )
        );

        return (int)$category->getId();
    }
}
