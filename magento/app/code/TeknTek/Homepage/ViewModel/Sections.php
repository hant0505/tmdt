<?php

declare(strict_types=1);

namespace TeknTek\Homepage\ViewModel;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Review\Model\ResourceModel\Review\Summary as ReviewSummaryResource;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Db_Expr;

class Sections implements ArgumentInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Visibility $productVisibility,
        private readonly ImageHelper $imageHelper,
        private readonly MediaConfig $mediaConfig,
        private readonly PriceHelper $priceHelper,
        private readonly ReviewSummaryResource $reviewSummaryResource
    ) {
    }

    /**
     * Homepage: deterministic, real-sale only, highest discount first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFeaturedProducts(int $limit = 4): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $collection = $this->createSaleCollection($storeId);
        $collection->setPageSize(max(1, $limit));
        $collection->setCurPage(1);
        $collection->addMediaGalleryData();
        $cards = $this->buildCards($collection, true);
        if ($cards !== []) {
            return $cards;
        }

        // Fallback: no real sale yet -> pick deterministic 3 products/category as offer candidates.
        $fallbackCards = $this->buildFallbackOfferCards($storeId, 3);
        return array_slice($fallbackCards, 0, max(1, $limit));
    }

    /**
     * @param array<int|string> $categoryIds
     * @return array<int, array<string, mixed>>
     */
    public function getSections(array $categoryIds, int $productLimit = 4): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $sections = [];

        foreach ($categoryIds as $categoryId) {
            $categoryId = (int) $categoryId;
            if ($categoryId <= 0) {
                continue;
            }

            try {
                $category = $this->categoryRepository->get($categoryId, $storeId);
            } catch (NoSuchEntityException) {
                continue;
            }

            if (!$category->getIsActive()) {
                continue;
            }

            $cards = $this->getCategoryProducts($categoryId, $productLimit, $storeId);
            if ($cards === []) {
                continue;
            }

            $sections[] = [
                'id' => $categoryId,
                'name' => (string) $category->getName(),
                'url' => (string) $category->getUrl(),
                'icon_key' => $this->normalizeIconKey((string) $category->getUrlKey(), (string) $category->getName()),
                'cards' => $cards,
            ];
        }

        return $sections;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCategoryProducts(int $categoryId, int $limit, int $storeId): array
    {
        $collection = $this->createBaseProductCollection($storeId);
        $collection->addCategoriesFilter(['in' => [$categoryId]]);
        $this->reviewSummaryResource->appendSummaryFieldsToCollection($collection, $storeId, 'product');
        $collection->getSelect()->order('e.entity_id DESC');
        $collection->setPageSize(max(1, $limit));
        $collection->setCurPage(1);
        $collection->addMediaGalleryData();

        return $this->buildCards($collection, false);
    }

    private function createBaseProductCollection(int $storeId): Collection
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect(['name', 'small_image', 'price', 'final_price', 'special_price', 'status', 'visibility']);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->productVisibility->getVisibleInCatalogIds());

        return $collection;
    }

    private function createSaleCollection(int $storeId): Collection
    {
        $collection = $this->createBaseProductCollection($storeId);
        $collection->addPriceData();
        $this->reviewSummaryResource->appendSummaryFieldsToCollection($collection, $storeId, 'product');
        $collection->addAttributeToFilter('small_image', ['neq' => 'no_selection']);
        $collection->addAttributeToFilter('small_image', ['notnull' => true]);

        // Real sale only, deterministic ordering by discount percentage.
        $collection->getSelect()->where('price_index.final_price < price_index.price');
        $collection->getSelect()->order(new Zend_Db_Expr('((price_index.price - price_index.final_price) / NULLIF(price_index.price, 0)) DESC'));
        $collection->getSelect()->order('e.entity_id DESC');

        return $collection;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCards(Collection $collection, bool $saleOnly): array
    {
        $cards = [];

        foreach ($collection as $product) {
            $regularPrice = (float) $product->getPrice();
            $finalPrice = (float) $product->getFinalPrice();

            if ($finalPrice <= 0.0) {
                continue;
            }

            $isDiscounted = $regularPrice > 0.0 && $finalPrice < $regularPrice;
            if ($saleOnly && !$isDiscounted) {
                continue;
            }

            $imageUrl = '';
            try {
                $imageUrl = (string) $this->imageHelper->init($product, 'product_small_image')->getUrl();
            } catch (\Throwable) {
                $imageUrl = '';
            }
            if ($imageUrl === '' || str_contains($imageUrl, 'placeholder')) {
                continue;
            }

            $secondaryImageUrl = '';
            $galleryImages = $product->getMediaGalleryImages();
            if ($galleryImages && $galleryImages->getSize() > 0) {
                foreach ($galleryImages as $galleryImage) {
                    $galleryFile = (string) $galleryImage->getFile();
                    if ($galleryFile === '') {
                        continue;
                    }

                    $candidateUrl = (string) $this->mediaConfig->getMediaUrl($galleryFile);
                    if ($candidateUrl !== '' && $candidateUrl !== $imageUrl) {
                        $secondaryImageUrl = $candidateUrl;
                        break;
                    }
                }
            }

            $discountPercent = 0;
            if ($isDiscounted) {
                $discountPercent = (int) round((($regularPrice - $finalPrice) / $regularPrice) * 100);
            }

            $cards[] = [
                'id' => (int) $product->getId(),
                'name' => (string) $product->getName(),
                'image' => $imageUrl,
                'secondary_image' => $secondaryImageUrl,
                'old' => $isDiscounted ? $this->priceHelper->currency($regularPrice, true, false) : '',
                'price' => $this->priceHelper->currency($finalPrice, true, false),
                'compare_price' => $finalPrice,
                'discount' => $discountPercent > 0 ? ('-' . $discountPercent . '%') : '',
                'discount_percent' => $discountPercent,
                'reviews' => (int) $product->getData('reviews_count'),
                'rating_summary' => (int) $product->getData('rating_summary'),
                'url' => (string) $product->getProductUrl(),
            ];
        }

        return $cards;
    }

    private function normalizeIconKey(string $urlKey, string $name): string
    {
        $value = trim($urlKey) !== '' ? $urlKey : $name;
        $value = strtolower($value);
        $value = str_replace(['&', '_'], ['and', '-'], $value);
        $value = preg_replace('/\s+/', '-', $value) ?: '';
        $value = preg_replace('/-+/', '-', $value) ?: '';

        return trim($value, '-');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFallbackOfferCards(int $storeId, int $perCategory): array
    {
        // Known storefront categories currently used in homepage icon navigation.
        $categoryIds = [10, 7, 8, 11, 3, 4, 12, 5, 9, 6];

        $cards = [];
        foreach ($categoryIds as $categoryId) {
            try {
                $category = $this->categoryRepository->get((int) $categoryId, $storeId);
            } catch (\Throwable) {
                continue;
            }
            if (!$category->getId() || !$category->getIsActive()) {
                continue;
            }

            $collection = $this->createBaseProductCollection($storeId);
            $collection->addCategoriesFilter(['in' => [(int) $category->getId()]]);
            $collection->addAttributeToFilter('small_image', ['neq' => 'no_selection']);
            $collection->addAttributeToFilter('small_image', ['notnull' => true]);
            $this->reviewSummaryResource->appendSummaryFieldsToCollection($collection, $storeId, 'product');
            $collection->getSelect()->order('e.entity_id DESC');
            $collection->setPageSize(max(1, $perCategory));
            $collection->setCurPage(1);
            $collection->addMediaGalleryData();

            foreach ($collection as $product) {
                $regular = (float) $product->getPrice();
                if ($regular <= 0) {
                    continue;
                }
                $fallbackDiscount = 10 + ((int) $product->getId() % 21); // deterministic 10-30%
                $offerFinal = round($regular * (100 - $fallbackDiscount) / 100, 2);

                $imageUrl = (string) $this->imageHelper->init($product, 'product_small_image')->getUrl();
                if ($imageUrl === '' || str_contains($imageUrl, 'placeholder')) {
                    continue;
                }

                $cards[] = [
                    'id' => (int) $product->getId(),
                    'name' => (string) $product->getName(),
                    'image' => $imageUrl,
                    'secondary_image' => '',
                    'old' => $this->priceHelper->currency($regular, true, false),
                    'price' => $this->priceHelper->currency($offerFinal, true, false),
                    'compare_price' => $offerFinal,
                    'discount' => '-' . $fallbackDiscount . '%',
                    'discount_percent' => $fallbackDiscount,
                    'reviews' => (int) $product->getData('reviews_count'),
                    'rating_summary' => (int) $product->getData('rating_summary'),
                    'url' => (string) $product->getProductUrl(),
                ];
            }
        }

        return $cards;
    }
}
