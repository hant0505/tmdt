<?php

declare(strict_types=1);

namespace TeknTek\CatalogTweaks\Model\Compare;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;

class AttributeProvider
{
    private const HIDE_IDENTICAL_NUMERIC_ROWS = true;

    /** @var array<string, Attribute[]> */
    private array $attributesCache = [];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EavConfig $eavConfig,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * @param Product[] $products
     * @return Attribute[]
     */
    public function getComparableAttributesForProducts(array $products, ?int $storeId = null): array
    {
        $setIds = [];
        foreach ($products as $product) {
            $setId = (int) $product->getAttributeSetId();
            if ($setId > 0) {
                $setIds[$setId] = $setId;
            }
        }

        if (!$setIds) {
            return [];
        }

        $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
        $cacheKey = $storeId . ':' . implode(',', array_values($setIds));
        if (isset($this->attributesCache[$cacheKey])) {
            return $this->attributesCache[$cacheKey];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['main_table' => $this->resource->getTableName('eav_attribute')])
            ->join(
                ['additional_table' => $this->resource->getTableName('catalog_eav_attribute')],
                'additional_table.attribute_id = main_table.attribute_id',
                []
            )
            ->joinLeft(
                ['al' => $this->resource->getTableName('eav_attribute_label')],
                'al.attribute_id = main_table.attribute_id AND al.store_id = ' . (int) $storeId,
                [
                    'store_label' => $connection->getCheckSql(
                        'al.value IS NULL',
                        'main_table.frontend_label',
                        'al.value'
                    )
                ]
            )
            ->where('additional_table.is_comparable = ?', 1)
            ->order(['additional_table.position ASC', 'main_table.frontend_label ASC']);

        $attributesData = $connection->fetchAll($select);
        if (!$attributesData) {
            return $this->attributesCache[$cacheKey] = [];
        }

        $entityType = Product::ENTITY;
        $this->eavConfig->importAttributesData($entityType, $attributesData);

        $attributes = [];
        foreach ($attributesData as $data) {
            $code = (string) ($data['attribute_code'] ?? '');
            if ($code === '' || $this->shouldSkipAttribute($code)) {
                continue;
            }

            $attribute = $this->eavConfig->getAttribute($entityType, $code);
            if ($attribute instanceof Attribute) {
                $attributes[$code] = $attribute;
            }
        }

        if (!$attributes) {
            $attributes = $this->getDataBackedAttributesForProducts($products, $storeId);
        }

        return $this->attributesCache[$cacheKey] = $attributes;
    }

    /**
     * @param int[] $productIds
     * @return Product[]
     */
    public function loadProductsWithComparableAttributes(array $productIds, ?int $storeId = null): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$productIds) {
            return [];
        }

        $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();

        $seedCollection = $this->productCollectionFactory->create();
        $seedCollection->setStoreId($storeId);
        $seedCollection->addAttributeToSelect(['name', 'small_image', 'price', 'special_price', 'final_price']);
        $seedCollection->addFieldToFilter('entity_id', ['in' => $productIds]);
        $seedCollection->load();

        $seedProducts = [];
        foreach ($seedCollection as $product) {
            $seedProducts[(int) $product->getId()] = $product;
        }

        $this->getComparableAttributesForProducts(array_values($seedProducts), $storeId);

        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect('*');
        $collection->addFieldToFilter('entity_id', ['in' => $productIds]);
        $collection->load();

        $productsById = [];
        foreach ($collection as $product) {
            $productsById[(int) $product->getId()] = $product;
        }

        $ordered = [];
        foreach ($productIds as $productId) {
            if (isset($productsById[$productId])) {
                $ordered[] = $productsById[$productId];
            }
        }

        return $ordered;
    }

    /**
     * Fallback for imported catalogs where technical attributes were not marked comparable.
     *
     * @param Product[] $products
     * @return Attribute[]
     */
    public function getDataBackedAttributesForProducts(array $products, ?int $storeId = null): array
    {
        $codesWithData = [];
        foreach ($products as $product) {
            foreach ((array) $product->getData() as $code => $value) {
                $code = (string) $code;
                if ($this->shouldSkipAttribute($code)) {
                    continue;
                }
                if (is_array($value)) {
                    $value = implode(', ', array_filter(array_map('strval', $value)));
                }
                if (trim((string) $value) !== '') {
                    $codesWithData[$code] = $code;
                }
            }
        }

        if (!$codesWithData) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
        $select = $connection->select()
            ->from(['main_table' => $this->resource->getTableName('eav_attribute')])
            ->join(
                ['additional_table' => $this->resource->getTableName('catalog_eav_attribute')],
                'additional_table.attribute_id = main_table.attribute_id',
                []
            )
            ->joinLeft(
                ['al' => $this->resource->getTableName('eav_attribute_label')],
                'al.attribute_id = main_table.attribute_id AND al.store_id = ' . (int) $storeId,
                [
                    'store_label' => $connection->getCheckSql(
                        'al.value IS NULL',
                        'main_table.frontend_label',
                        'al.value'
                    )
                ]
            )
            ->where('main_table.attribute_code IN (?)', array_values($codesWithData))
            ->where('main_table.backend_type IN (?)', ['varchar', 'int', 'text', 'decimal'])
            ->order(['additional_table.is_comparable DESC', 'additional_table.position ASC', 'main_table.frontend_label ASC']);

        $attributesData = $connection->fetchAll($select);
        if (!$attributesData) {
            return [];
        }

        $entityType = Product::ENTITY;
        $this->eavConfig->importAttributesData($entityType, $attributesData);

        $attributes = [];
        foreach ($attributesData as $data) {
            $code = (string) ($data['attribute_code'] ?? '');
            if ($code === '' || $this->shouldSkipAttribute($code)) {
                continue;
            }

            $attribute = $this->eavConfig->getAttribute($entityType, $code);
            if (!$attribute instanceof Attribute) {
                continue;
            }

            $label = trim((string) ($attribute->getStoreLabel() ?: $attribute->getFrontendLabel()));
            if ($label === '') {
                continue;
            }

            $attributes[$code] = $attribute;
            if (count($attributes) >= 80) {
                break;
            }
        }

        return $attributes;
    }

    /**
     * @param Product[] $products
     * @param Attribute[] $attributes
     * @return array<int, array{attribute: Attribute, code: string, label: string, values: string[]}>
     */
    public function buildRows(array $products, array $attributes): array
    {
        $rows = [];
        foreach ($attributes as $attribute) {
            $values = [];
            $hasValueForAllProducts = !empty($products);

            foreach ($products as $product) {
                $value = $this->formatValue($product, $attribute);
                if (!$this->isMeaningfulValue($value)) {
                    $hasValueForAllProducts = false;
                }
                $values[] = $value !== '' ? $value : '-';
            }

            if (!$hasValueForAllProducts) {
                continue;
            }

            if ($this->shouldHideRedundantRow($values, $attribute)) {
                continue;
            }

            $rows[] = [
                'attribute' => $attribute,
                'code' => (string) $attribute->getAttributeCode(),
                'label' => (string) ($attribute->getStoreLabel() ?: $attribute->getFrontendLabel() ?: $attribute->getAttributeCode()),
                'values' => $values
            ];
        }

        return $rows;
    }

    /**
     * Build compare rows from imported specification tables stored in product descriptions.
     *
     * @param Product[] $products
     * @return array<int, array{attribute: null, code: string, label: string, values: string[]}>
     */
    public function buildDescriptionSpecRows(array $products, int $limit = 80): array
    {
        $specsByProduct = [];
        $labels = [];

        foreach ($products as $index => $product) {
            $specs = $this->extractSpecsFromHtml((string) $product->getDescription());
            $specsByProduct[$index] = $specs;
            foreach ($specs as $code => $row) {
                $labels[$code] = $row['label'];
            }
        }

        if (!$labels) {
            return [];
        }

        $rows = [];
        foreach ($labels as $code => $label) {
            $values = [];
            $hasValueForAllProducts = !empty($products);

            foreach ($products as $index => $product) {
                $value = (string) ($specsByProduct[$index][$code]['value'] ?? '');
                if (!$this->isMeaningfulValue($value)) {
                    $hasValueForAllProducts = false;
                }
                $values[] = $value !== '' ? $value : '-';
            }

            if (!$hasValueForAllProducts) {
                continue;
            }

            if ($this->shouldHideRedundantSpecRow($values)) {
                continue;
            }

            $rows[] = [
                'attribute' => null,
                'code' => $code,
                'label' => $label,
                'values' => $values
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    public function formatValue(Product $product, Attribute $attribute): string
    {
        $code = (string) $attribute->getAttributeCode();
        if (!$product->hasData($code)) {
            return '';
        }

        $rawValue = $product->getData($code);
        if ($rawValue === null || $rawValue === '') {
            return '';
        }

        $frontendInput = (string) $attribute->getFrontendInput();
        if ($frontendInput === 'price' || $this->isPriceAttribute($attribute)) {
            return (string) $this->priceCurrency->format((float) $rawValue, false, 2);
        }

        if ($code === 'weight' || str_contains(strtolower($code), 'weight')) {
            return $this->formatNumber((float) $rawValue) . ' kg';
        }

        if ($attribute->getSourceModel() || in_array($frontendInput, ['select', 'boolean', 'multiselect'], true)) {
            $labelValue = $attribute->getFrontend()->getValue($product);
            if (is_array($labelValue)) {
                $labelValue = implode(', ', array_filter(array_map('strval', $labelValue)));
            }
            $labelValue = trim((string) $labelValue);
            if ($labelValue !== '' && $labelValue !== 'N/A') {
                return $labelValue;
            }
        }

        if (is_array($rawValue)) {
            return implode(', ', array_filter(array_map('strval', $rawValue)));
        }

        if (is_numeric($rawValue)) {
            return $this->formatNumber((float) $rawValue);
        }

        return trim((string) $rawValue);
    }

    public function isMeaningfulValue(string $value): bool
    {
        $normalized = strtolower(trim($value));
        return !in_array($normalized, ['', '-', 'n/a', 'no selection'], true);
    }

    /**
     * @param Product[] $products
     * @return int[]
     */
    public function getProductIds(array $products): array
    {
        return array_values(array_filter(array_map(static function (Product $product): int {
            return (int) $product->getId();
        }, $products)));
    }

    private function isPriceAttribute(Attribute $attribute): bool
    {
        return strtolower((string) $attribute->getFrontendInput()) === 'price'
            || in_array((string) $attribute->getBackendType(), ['decimal'], true)
            && str_contains(strtolower((string) $attribute->getAttributeCode()), 'price');
    }

    private function shouldSkipAttribute(string $code): bool
    {
        return in_array($code, [
            'category_ids',
            'custom_design',
            'custom_design_from',
            'custom_design_to',
            'custom_layout',
            'custom_layout_update',
            'custom_layout_update_file',
            'enable_googlecheckout',
            'gallery',
            'gift_message_available',
            'small_image',
            'thumbnail',
            'image',
            'media_gallery',
            'name',
            'sku',
            'msrp_display_actual_price_type',
            'news_from_date',
            'news_to_date',
            'options_container',
            'page_layout',
            'description',
            'short_description',
            'meta_title',
            'meta_keyword',
            'meta_description',
            'quantity_and_stock_status',
            'status',
            'swatch_image',
            'tax_class_id',
            'url_key',
            'url_path',
            'visibility',
            'has_options',
            'required_options',
            'created_at',
            'updated_at',
            'asin',
            'best_sellers_rank',
            'customer_reviews',
            'date_first_available',
            'is_returnable',
            'merchant_center_category',
            'gtin',
            'mpn'
        ], true);
    }

    private function shouldHideRedundantRow(array $values, Attribute $attribute): bool
    {
        if (!self::HIDE_IDENTICAL_NUMERIC_ROWS) {
            return false;
        }

        if (!$this->isNumericAttribute($attribute)) {
            return false;
        }

        return $this->allValuesAreSame($values);
    }

    private function shouldHideRedundantSpecRow(array $values): bool
    {
        if (!self::HIDE_IDENTICAL_NUMERIC_ROWS || !$this->allValuesAreSame($values)) {
            return false;
        }

        return $this->looksPureNumericWithUnit((string) reset($values));
    }

    private function isNumericAttribute(Attribute $attribute): bool
    {
        $backendType = (string) $attribute->getBackendType();
        $frontendInput = (string) $attribute->getFrontendInput();

        return in_array($backendType, ['decimal', 'int'], true)
            && !in_array($frontendInput, ['select', 'multiselect', 'boolean'], true);
    }

    private function allValuesAreSame(array $values): bool
    {
        $normalized = [];
        foreach ($values as $value) {
            $normalized[] = $this->normalizeComparableValue((string) $value);
        }

        return count(array_unique($normalized)) === 1;
    }

    private function normalizeComparableValue(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/\s+/', ' ', $value);

        if ($this->looksNumericValue($value)) {
            return (string) (float) preg_replace('/[^0-9.\-]+/', '', $value);
        }

        return $value;
    }

    private function looksNumericValue(string $value): bool
    {
        $number = preg_replace('/[^0-9.\-]+/', '', trim($value));
        return $number !== '' && is_numeric($number);
    }

    private function looksPureNumericWithUnit(string $value): bool
    {
        return (bool) preg_match('/^\s*-?\d+(?:\.\d+)?\s*(kg|g|lb|lbs|mm|cm|m|in|inch|inches|count|mah|w|wh|hz|ghz|mhz|gb|tb|mb)?\s*$/i', $value);
    }

    private function formatNumber(float $number): string
    {
        if (abs($number - round($number)) < 0.000001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    /**
     * @return array<string, array{label: string, value: string}>
     */
    private function extractSpecsFromHtml(string $html): array
    {
        if (trim($html) === '' || stripos($html, '<table') === false) {
            return [];
        }

        $specs = [];
        if (!preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $rows)) {
            return [];
        }

        foreach ($rows[1] as $rowHtml) {
            if (!preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/is', $rowHtml, $cells) || count($cells[1]) < 2) {
                continue;
            }

            $rawLabel = $this->cleanHtmlText((string) $cells[1][0]);
            $value = $this->cleanHtmlText((string) $cells[1][1]);
            if ($rawLabel === '' || !$this->isMeaningfulValue($value)) {
                continue;
            }

            $code = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $rawLabel));
            $code = trim($code, '_');
            if ($code === '' || $this->shouldSkipAttribute($code)) {
                continue;
            }

            $specs[$code] = [
                'label' => $this->humanizeSpecLabel($rawLabel),
                'value' => $value
            ];
        }

        return $specs;
    }

    private function cleanHtmlText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private function humanizeSpecLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = str_replace(['_', '-'], ' ', $label);
        $label = (string) preg_replace('/\s+/', ' ', $label);

        $map = [
            'gps' => 'GPS',
            'ram' => 'RAM',
            'usb' => 'USB',
            'wifi' => 'Wi-Fi',
            'wi fi' => 'Wi-Fi',
            'ios' => 'iOS',
            'ip' => 'IP'
        ];

        $words = explode(' ', $label);
        foreach ($words as &$word) {
            $word = $map[$word] ?? ucfirst($word);
        }
        unset($word);

        return implode(' ', $words);
    }
}
