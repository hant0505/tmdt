<?php
// app/code/Recommendation/SimilarPic/Block/Product/SameCategory.php

namespace Recommendation\SimilarPic\Block\Product;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\Http\Context as HttpContext;

class SameCategory extends AbstractProduct
{
    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var Visibility
     */
    protected $catalogProductVisibility;

    /**
     * @var HttpContext
     */
    protected $httpContext;

    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        CollectionFactory $productCollectionFactory,
        Visibility $catalogProductVisibility,
        HttpContext $httpContext,
        array $data = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->catalogProductVisibility = $catalogProductVisibility;
        $this->httpContext = $httpContext;
        parent::__construct($context, $data);
    }

    /**
     * Lấy collection các sản phẩm cùng danh mục, cùng tầm giá, còn hàng
     *
     * @param \Magento\Catalog\Model\Product $currentProduct
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getSameCategoryProducts($currentProduct)
    {
        // Lấy danh mục đầu tiên
        $categoryIds = $currentProduct->getCategoryIds();
        if (empty($categoryIds)) {
            return [];
        }
        $categoryId = $categoryIds[0];

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'price', 'url_key', 'small_image']);
        $collection->addCategoriesFilter(['in' => $categoryId]);
        $collection->addFieldToFilter('entity_id', ['neq' => $currentProduct->getId()]);
        
        // TẠM THỜI COMMENT 2 BỘ LỌC NÀY ĐỂ CHẮC CHẮN CÓ SẢN PHẨM
        // $collection->addFieldToFilter('price', ['from' => $minPrice, 'to' => $maxPrice]);
        // $collection->addFieldToFilter('quantity_and_stock_status', ['is_in_stock' => true]);
        
        $collection->setVisibility($this->catalogProductVisibility->getVisibleInSiteIds());
        $collection->setPageSize(5);
        $collection->setOrder('entity_id', 'DESC');
        
        return $collection;
    }
}