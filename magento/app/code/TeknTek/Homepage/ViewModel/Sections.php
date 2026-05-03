<?php

declare(strict_types=1);

namespace TeknTek\Homepage\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Review\Model\ReviewFactory;
use Magento\Review\Model\ResourceModel\Review\Summary as ReviewSummaryResource;
use Magento\Store\Model\StoreManagerInterface;

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
        private readonly ReviewSummaryResource $reviewSummaryResource,
        private readonly ReviewFactory $reviewFactory
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFeaturedProducts(int $limit = 4): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $collection = $this->createBaseProductCollection($storeId);
        $this->reviewSummaryResource->appendSummaryFieldsToCollection($collection, $storeId, 'product');
        $collection->getSelect()->order('rating_summary DESC');
        $collection->getSelect()->order('reviews_count DESC');
        $collection->getSelect()->order('e.entity_id DESC');
        $collection->setPageSize(max(1, $limit));
        $collection->setCurPage(1);
        $collection->addMediaGalleryData();

        return $this->buildCards($collection);
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
        $collection->getSelect()->order('rating_summary DESC');
        $collection->getSelect()->order('reviews_count DESC');
        $collection->getSelect()->order('e.entity_id DESC');
        $collection->setPageSize(max(1, $limit));
        $collection->setCurPage(1);
        $collection->addMediaGalleryData();

        return $this->buildCards($collection);
    }

    private function createBaseProductCollection(int $storeId): \Magento\Catalog\Model\ResourceModel\Product\Collection
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect(['name', 'small_image', 'price', 'final_price', 'status', 'visibility']);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->productVisibility->getVisibleInCatalogIds());

        return $collection;
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
     * @return array<int, array<string, mixed>>
     */
    private function buildCards(\Magento\Catalog\Model\ResourceModel\Product\Collection $collection): array
    {
        $cards = [];
        foreach ($collection as $product) {
            $regularPrice = (float) $product->getPrice();
            $finalPrice = (float) $product->getFinalPrice();
            if ($finalPrice <= 0.0) {
                continue;
            }

            $imageUrl = '';
            try {
                $imageUrl = (string) $this->imageHelper->init($product, 'product_small_image')->getUrl();
            } catch (\Throwable) {
                $imageUrl = '';
            }

            if ($imageUrl === '') {
                $imageUrl = (string) $this->imageHelper->getDefaultPlaceholderUrl('small_image');
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

            $discountLabel = '';
            if ($regularPrice > 0 && $finalPrice < $regularPrice) {
                $percent = (int) round((($regularPrice - $finalPrice) / $regularPrice) * 100);
                if ($percent > 0) {
                    $discountLabel = $percent . '%';
                }
            }

            $reviewsCount = (int) $product->getData('reviews_count');
            $ratingSummary = (int) $product->getData('rating_summary');

            if ($reviewsCount === 0 && $ratingSummary === 0) {
                try {
                    $this->reviewFactory->create()->getEntitySummary($product, (int) $this->storeManager->getStore()->getId());
                    $reviewsCount = max($reviewsCount, (int) $product->getReviewsCount());
                    $ratingSummary = max($ratingSummary, (int) $product->getRatingSummary());
                } catch (\Throwable) {
                    // Keep zero values if summary lookup fails.
                }
            }

            if ($reviewsCount === 0 && $ratingSummary === 0) {
                // Match the PDP fallback while the catalog still has no native Magento reviews.
                $reviewsCount = 5;
                $ratingSummary = 84;
            }

            $cards[] = [
                'id' => (int) $product->getId(),
                'name' => (string) $product->getName(),
                'image' => $imageUrl,
                'secondary_image' => $secondaryImageUrl,
                'old' => $regularPrice > $finalPrice ? $this->priceHelper->currency($regularPrice, true, false) : '',
                'price' => $this->priceHelper->currency($finalPrice, true, false),
                'compare_price' => $finalPrice,
                'discount' => $discountLabel,
                'reviews' => $reviewsCount,
                'rating_summary' => $ratingSummary,
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
}
