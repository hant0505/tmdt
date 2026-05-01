<?php

declare(strict_types=1);

namespace TeknTek\CatalogTweaks\Plugin\Catalog;

use Magento\Catalog\Block\Product\Compare\ListCompare;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Phrase;
use TeknTek\CatalogTweaks\Model\Compare\AttributeProvider;

class ListComparePlugin
{
    public function __construct(
        private readonly AttributeProvider $attributeProvider
    ) {
    }

    public function afterGetItems(ListCompare $subject, $items)
    {
        $attributes = $items->getComparableAttributes();
        $codes = [];
        foreach ($attributes as $attribute) {
            $codes[] = (string) $attribute->getAttributeCode();
        }

        if ($codes && method_exists($items, 'isLoaded') && !$items->isLoaded()) {
            $items->addAttributeToSelect(array_values(array_unique($codes)));
        }

        return $items;
    }

    /**
     * @param Attribute[] $attributes
     * @return Attribute[]
     */
    public function afterGetAttributes(ListCompare $subject, array $attributes): array
    {
        $products = [];
        foreach ($subject->getItems() as $item) {
            if ($item instanceof Product) {
                $products[] = $item;
            }
        }

        if (!$products) {
            return $attributes;
        }

        $providerAttributes = $this->attributeProvider->getComparableAttributesForProducts($products);
        $rows = $this->attributeProvider->buildRows($products, $providerAttributes ?: $attributes);
        $filtered = [];
        foreach ($rows as $row) {
            $attribute = $row['attribute'] ?? null;
            if ($attribute instanceof Attribute) {
                $filtered[(string) $attribute->getAttributeCode()] = $attribute;
            }
        }

        return $filtered;
    }

    public function aroundGetProductAttributeValue(
        ListCompare $subject,
        callable $proceed,
        Product $product,
        Attribute $attribute
    ): Phrase|string {
        $value = $this->attributeProvider->formatValue($product, $attribute);
        return $value !== '' ? $value : '-';
    }

    public function aroundHasAttributeValueForProducts(
        ListCompare $subject,
        callable $proceed,
        Attribute $attribute
    ): bool {
        $hasProducts = false;
        foreach ($subject->getItems() as $item) {
            if (!$item instanceof Product) {
                continue;
            }

            $hasProducts = true;
            if (!$this->attributeProvider->isMeaningfulValue($this->attributeProvider->formatValue($item, $attribute))) {
                return false;
            }
        }

        return $hasProducts;
    }
}
