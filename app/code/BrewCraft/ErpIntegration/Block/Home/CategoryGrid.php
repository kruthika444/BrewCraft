<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Block\Home;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class CategoryGrid extends Template
{
    /**
     * Categories we want to display on BrewCraft homepage.
     */
    private const CATEGORY_CODES = [
        'COFFEE',
        'MACHINES',
        'GRINDERS',
        'BREWING',
        'ACCESSORIES',
        'COMMERCIAL'
    ];

    public function __construct(
        Context $context,
        private readonly CollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCategories(): array
    {
        $storeId = (int)$this->storeManager
            ->getStore()
            ->getId();

        $collection = $this->categoryCollectionFactory
            ->create();

        $collection->setStoreId($storeId);

        $collection->addAttributeToSelect([
            'name',
            'url_key',
            'url_path',
            'image',
            'erp_category_code'
        ]);

        $collection->addAttributeToFilter(
            'erp_category_code',
            ['in' => self::CATEGORY_CODES]
        );

        $collection->addAttributeToFilter(
            'is_active',
            1
        );

        $categoryMap = [];

        foreach ($collection as $category) {
            $erpCode = (string)$category->getData(
                'erp_category_code'
            );

            $categoryMap[$erpCode] = $category;
        }

        $result = [];

        foreach (self::CATEGORY_CODES as $code) {
            if (isset($categoryMap[$code])) {
                $result[] = $categoryMap[$code];
            }
        }

        return $result;
    }

    public function getCategoryImageUrl(Category $category): ?string
    {
        $imageUrl = $category->getImageUrl();

        if (!$imageUrl) {
            return null;
        }

        return (string)$imageUrl;
    }
}
