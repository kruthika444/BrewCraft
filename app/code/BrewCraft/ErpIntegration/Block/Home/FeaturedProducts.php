<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Block\Home;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class FeaturedProducts extends Template
{
    private const FEATURED_CATEGORY_URL_KEY = 'featured-products';
    private const PRODUCT_LIMIT = 4;

    public function __construct(
        Context $context,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly Visibility $productVisibility,
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly CartHelper $cartHelper,
        private readonly FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getProducts(): array
    {

        $category = $this->getFeaturedCategory();

        if (!$category || !$category->getId()) {
            return [];
        }

        $collection = $this->productCollectionFactory->create();

        $collection->addAttributeToSelect([
            'name',
            'sku',
            'image',
            'small_image',
            'thumbnail',
            'price',
            'special_price',
            'special_from_date',
            'special_to_date'
        ]);

        $collection->addCategoryFilter($category);

        $collection->addStoreFilter();

        $collection->setVisibility(
            $this->productVisibility->getVisibleInCatalogIds()
        );

        $collection->addAttributeToFilter('status', 1);

        $collection->addUrlRewrite();

        $collection->setPageSize(self::PRODUCT_LIMIT);
        $collection->setCurPage(1);

        return $collection->getItems();
    }

    private function getFeaturedCategory(): ?\Magento\Catalog\Model\Category
    {
        $collection = $this->categoryCollectionFactory->create();

        $collection->addAttributeToSelect([
            'name',
            'url_key'
        ]);

        $collection->addAttributeToFilter(
            'url_key',
            self::FEATURED_CATEGORY_URL_KEY
        );

        $collection->addAttributeToFilter(
            'is_active',
            1
        );

        $collection->setPageSize(1);

        $category = $collection->getFirstItem();

        return $category->getId() ? $category : null;
    }

    public function getProductImageUrl(Product $product): string
    {
        return $this->imageHelper
            ->init($product, 'category_page_grid')
            ->getUrl();
    }

    public function getRegularPrice(Product $product): float
    {
        return (float)$product->getPrice();
    }

    public function getFinalPrice(Product $product): float
    {
        return (float)$product->getFinalPrice();
    }

    public function hasSpecialPrice(Product $product): bool
    {
        $regularPrice = $this->getRegularPrice($product);
        $finalPrice = $this->getFinalPrice($product);

        return $finalPrice > 0
            && $finalPrice < $regularPrice;
    }

    public function formatPrice(float $price): string
    {
        return $this->priceCurrency->convertAndFormat(
            $price,
            false
        );
    }

    public function getAddToCartUrl(Product $product): string
    {
        return $this->cartHelper->getAddUrl($product);
    }

    public function getFormKeyValue(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getFeaturedCategoryUrl(): string
    {
        $category = $this->getFeaturedCategory();

        if (!$category || !$category->getId()) {
            return '#';
        }

        return (string)$category->getUrl();
    }
}
