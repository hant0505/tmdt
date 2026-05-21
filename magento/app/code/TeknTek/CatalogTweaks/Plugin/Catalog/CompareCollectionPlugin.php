<?php

declare(strict_types=1);

namespace TeknTek\CatalogTweaks\Plugin\Catalog;

use Magento\Catalog\Model\ResourceModel\Product\Compare\Item\Collection;

class CompareCollectionPlugin
{
    public function afterLoadComparableAttributes(Collection $subject, Collection $result): Collection
    {
        $attributes = $subject->getComparableAttributes();
        $codes = [];
        foreach ($attributes as $attribute) {
            $codes[] = (string) $attribute->getAttributeCode();
        }

        if ($codes) {
            $subject->addAttributeToSelect(array_values(array_unique($codes)));
        }

        return $result;
    }
}
