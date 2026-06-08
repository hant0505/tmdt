<?php

declare(strict_types=1);

namespace TeknTek\Homepage\Model;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;

class OfferPriceProvider
{
    private const CACHE_KEY = 'tekntek_offer_product_map_v2_%d';
    private const CACHE_TAG = 'TEKNTEK_OFFER_PRODUCTS';
    private const OFFER_CATEGORY_IDS = [10, 7, 8, 11, 3, 4, 12, 5, 9, 6];

    /** @var array<int,array<int,int>> */
    private array $runtimeMapByStore = [];

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $jsonSerializer,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly Visibility $productVisibility
    ) {
    }

    public function getOfferPriceByProductId(int $productId): ?float
    {
        return null;
    }

    /**
     * @return array<int, int>
     */
    public function getOfferProductDiscountMap(?int $storeId = null): array
    {
        $storeId = $storeId !== null ? $storeId : (int) $this->storeManager->getStore()->getId();
        if (isset($this->runtimeMapByStore[$storeId])) {
            return $this->runtimeMapByStore[$storeId];
        }

        $cacheKey = sprintf(self::CACHE_KEY, $storeId);
        $raw = $this->cache->load($cacheKey);
        if (is_string($raw) && $raw !== '') {
            try {
                $cached = $this->jsonSerializer->unserialize($raw);
                if (is_array($cached)) {
                    $normalized = $this->normalizeDiscountMap($cached);
                    $this->runtimeMapByStore[$storeId] = $normalized;
                    return $normalized;
                }
            } catch (\Throwable) {
            }
        }

        $built = $this->buildOfferProductDiscountMap($storeId);
        $this->runtimeMapByStore[$storeId] = $built;
        $this->cache->save(
            $this->jsonSerializer->serialize($built),
            $cacheKey,
            [self::CACHE_TAG],
            3600
        );

        return $built;
    }

    public function getOfferDiscountPercentByProductId(int $productId, ?int $storeId = null): ?int
    {
        if ($productId <= 0) {
            return null;
        }
        $map = $this->getOfferProductDiscountMap($storeId);
        return isset($map[$productId]) ? (int) $map[$productId] : null;
    }

    public function getDiscountedPriceForProduct(\Magento\Catalog\Model\Product $product, ?int $storeId = null): ?float
    {
        $productId = (int) $product->getId();
        if ($productId <= 0) {
            return null;
        }
        $discountPercent = $this->getOfferDiscountPercentByProductId($productId, $storeId);
        if ($discountPercent === null || $discountPercent <= 0) {
            return null;
        }
        $basePrice = (float) $product->getPrice();
        if ($basePrice <= 0) {
            return null;
        }
        $discounted = $basePrice * (1 - ($discountPercent / 100));
        if ($discounted <= 0 || $discounted >= $basePrice) {
            return null;
        }
        return round($discounted, 2);
    }

    /**
     * @param array<mixed> $map
     * @return array<int, int>
     */
    private function normalizeDiscountMap(array $map): array
    {
        $normalized = [];
        foreach ($map as $productId => $discountPercent) {
            $id = (int) $productId;
            $percent = (int) $discountPercent;
            if ($id > 0 && $percent > 0 && $percent < 100) {
                $normalized[$id] = $percent;
            }
        }
        return $normalized;
    }

    /**
     * @return array<int, int>
     */
    private function buildOfferProductDiscountMap(int $storeId): array
    {
        $discountMap = [];
        $targetTotal = count(self::OFFER_CATEGORY_IDS) * 3;
        foreach (self::OFFER_CATEGORY_IDS as $categoryId) {
            $collection = $this->productCollectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToSelect(['price', 'small_image', 'entity_id']);
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
            $collection->setVisibility($this->productVisibility->getVisibleInCatalogIds());
            $collection->addAttributeToFilter('small_image', ['neq' => 'no_selection']);
            $collection->addAttributeToFilter('small_image', ['notnull' => true]);
            $collection->addCategoriesFilter(['in' => [(int) $categoryId]]);
            $collection->getSelect()->order('e.entity_id DESC');
            $collection->setPageSize(3);
            $collection->setCurPage(1);

            foreach ($collection as $product) {
                $productId = (int) $product->getId();
                $price = (float) $product->getPrice();
                if ($productId <= 0 || $price <= 0 || isset($discountMap[$productId])) {
                    continue;
                }
                $discountMap[$productId] = 10 + ($productId % 21);
            }
        }

        if (count($discountMap) < $targetTotal) {
            $collection = $this->productCollectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToSelect(['price', 'small_image', 'entity_id']);
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
            $collection->setVisibility($this->productVisibility->getVisibleInCatalogIds());
            $collection->addAttributeToFilter('small_image', ['neq' => 'no_selection']);
            $collection->addAttributeToFilter('small_image', ['notnull' => true]);
            $collection->addCategoriesFilter(['in' => self::OFFER_CATEGORY_IDS]);
            $collection->getSelect()->order('e.entity_id DESC');
            $collection->setPageSize(300);
            $collection->setCurPage(1);

            foreach ($collection as $product) {
                if (count($discountMap) >= $targetTotal) {
                    break;
                }
                $productId = (int) $product->getId();
                $price = (float) $product->getPrice();
                if ($productId <= 0 || $price <= 0 || isset($discountMap[$productId])) {
                    continue;
                }
                $discountMap[$productId] = 10 + ($productId % 21);
            }
        }

        return $discountMap;
    }

    /**
     * @return int[]
     */
    public static function getOfferCategoryIds(): array
    {
        return self::OFFER_CATEGORY_IDS;
    }
}
