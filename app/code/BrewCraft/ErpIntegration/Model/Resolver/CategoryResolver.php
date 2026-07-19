<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Resolver;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

class CategoryResolver
{
    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory,
        private readonly CategoryFactory $categoryFactory
    ) {
    }

    /**
     * Find Magento category using ERP category code.
     */
    public function getByErpCode(string $erpCategoryCode): ?Category
    {
        $collection = $this->categoryCollectionFactory->create();

        $collection->addAttributeToSelect('*');

        $collection->addAttributeToFilter(
            'erp_category_code',
            $erpCategoryCode
        );

        /** @var Category $category */
        $category = $collection->getFirstItem();

        if (!$category->getId()) {
            return null;
        }

        return $category;
    }

    /**
     * Create empty Magento category model.
     */
    public function create(): Category
    {
        return $this->categoryFactory->create();
    }
}
