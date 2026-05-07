<?php

namespace TeknTek\SignupFlow\Plugin\Category;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer\Category\ItemCollectionProvider;

class IncludeChildProducts
{
    /**
     * Make non-anchor parent categories behave like anchor categories when they have child categories.
     * This keeps child-category products visible on the parent category page without changing admin data.
     *
     * @param ItemCollectionProvider $subject
     * @param callable $proceed
     * @param Category $category
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function aroundGetCollection(ItemCollectionProvider $subject, callable $proceed, Category $category)
    {
        $children = $category->getChildrenCategories();
        if ($children && $children->getSize() > 0 && !$category->getIsAnchor()) {
            $category = clone $category;
            $category->setIsAnchor(true);
        }

        return $proceed($category);
    }
}
