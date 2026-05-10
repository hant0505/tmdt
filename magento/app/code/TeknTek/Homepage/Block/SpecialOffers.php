<?php

declare(strict_types=1);

namespace TeknTek\Homepage\Block;

use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Review\Model\ResourceModel\Review\Summary as ReviewSummaryResource;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Block\Html\Pager;
use TeknTek\Homepage\Model\OfferPriceProvider;
use Zend_Db_Expr;

class SpecialOffers extends Template
{
    private ?Collection $collection = null;
    /** @var array<int,float> */
    private array $fallbackOfferPriceMap = [];

    public function __construct(
        Context $context,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly Visibility $productVisibility,
        private readonly ReviewSummaryResource $reviewSummaryResource,
        private readonly ImageHelper $imageHelper,
        private readonly PriceHelper $priceHelper,
        private readonly FormKey $formKey,
        private readonly StoreManagerInterface $storeManager,
        private readonly OfferPriceProvider $offerPriceProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getLoadedProductCollection(): Collection
    {
        if ($this->collection !== null) {
            return $this->collection;
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect(['name', 'small_image', 'price', 'final_price', 'special_price', 'created_at']);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->productVisibility->getVisibleInCatalogIds());
        $collection->addPriceData();
        $this->reviewSummaryResource->appendSummaryFieldsToCollection($collection, $storeId, 'product');
        $collection->getSelect()->where('price_index.final_price < price_index.price');

        $selectedCategoryIds = $this->getSelectedCategoryIds();
        if ($selectedCategoryIds !== []) {
            $collection->addCategoriesFilter(['in' => $selectedCategoryIds]);
        }

        $sort = (string) $this->getRequest()->getParam('sort', 'discount_desc');
        switch ($sort) {
            case 'discount_asc':
                $collection->getSelect()->order(new Zend_Db_Expr('((price_index.price - price_index.final_price) / NULLIF(price_index.price, 0)) ASC'));
                break;
            case 'price_asc':
                $collection->setOrder('final_price', 'ASC');
                break;
            case 'price_desc':
                $collection->setOrder('final_price', 'DESC');
                break;
            case 'newest':
                $collection->setOrder('created_at', 'DESC');
                break;
            case 'discount_desc':
            default:
                $collection->getSelect()->order(new Zend_Db_Expr('((price_index.price - price_index.final_price) / NULLIF(price_index.price, 0)) DESC'));
                break;
        }
        $collection->setOrder('entity_id', 'DESC');

        $limit = 10;
        $collection->setPageSize($limit);
        $collection->setCurPage(max(1, (int) $this->getRequest()->getParam('p', 1)));

        if ((int) $collection->getSize() === 0) {
            $collection = $this->buildFallbackOfferCollection($storeId, $selectedCategoryIds);
            $collection->setPageSize($limit);
            $collection->setCurPage(max(1, (int) $this->getRequest()->getParam('p', 1)));
        }

        $this->collection = $collection;

        return $this->collection;
    }

    protected function _prepareLayout(): self
    {
        parent::_prepareLayout();
        if ($this->getNameInLayout() !== 'tekntek.specialoffers') {
            return $this;
        }

        $toolbar = $this->getChildBlock('toolbar');
        if (!$toolbar) {
            $toolbar = $this->getLayout()->createBlock(Toolbar::class);
            if ($toolbar) {
                $this->setChild('toolbar', $toolbar);
            }
        }
        if ($toolbar) {
            $toolbar->setCollection($this->getLoadedProductCollection());
        }

        $pager = $this->getChildBlock('pager');
        if (!$pager) {
            $pager = $this->getLayout()->createBlock(Pager::class);
            if ($pager) {
                $this->setChild('pager', $pager);
            }
        }
        if ($pager) {
            $pager->setAvailableLimit([10 => 10]);
            $pager->setShowPerPage(false);
            $pager->setCollection($this->getLoadedProductCollection());
        }

        return $this;
    }

    public function getPagerHtml(): string
    {
        return (string) $this->getChildHtml('pager');
    }

    public function getToolbarHtml(): string
    {
        return (string) $this->getChildHtml('toolbar');
    }

    /**
     * @return array<int, array{id:int,label:string,url_key:string}>
     */
    public function getOfferCategories(): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $offerCategoryIds = OfferPriceProvider::getOfferCategoryIds();
        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(['name', 'url_key']);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addFieldToFilter('entity_id', ['in' => $offerCategoryIds]);

        $items = [];
        $seen = [];
        foreach ($collection as $category) {
            $urlKey = (string) $category->getUrlKey();
            if ($urlKey === '' || isset($seen[$urlKey])) {
                continue;
            }
            $seen[$urlKey] = true;
            $items[] = [
                'id' => (int) $category->getId(),
                'label' => (string) $category->getName(),
                'url_key' => $urlKey,
            ];
        }

        usort($items, static fn(array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $items;
    }

    /**
     * @return string[]
     */
    public function getSelectedCategoryKeys(): array
    {
        $raw = $this->getRequest()->getParam('cat', '');
        $keys = [];

        if (is_array($raw)) {
            $keys = array_map(static fn($v): string => trim((string) $v), $raw);
        } elseif (is_string($raw) && $raw !== '') {
            $keys = array_map(static fn(string $v): string => trim($v), explode(',', $raw));
        }

        $keys = array_filter($keys, static fn(string $v): bool => $v !== '');
        $keys = array_values(array_unique(array_map('strtolower', $keys)));

        return $keys;
    }

    /**
     * @return int[]
     */
    public function getSelectedCategoryIds(): array
    {
        $keys = $this->getSelectedCategoryKeys();
        if ($keys === []) {
            return [];
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(['url_key']);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addAttributeToFilter('url_key', ['in' => $keys]);

        return array_map(static fn($category): int => (int) $category->getId(), $collection->getItems());
    }

    public function getImageUrl(\Magento\Catalog\Model\Product $product): string
    {
        try {
            return (string) $this->imageHelper->init($product, 'product_small_image')->getUrl();
        } catch (\Throwable) {
            return (string) $this->imageHelper->getDefaultPlaceholderUrl('small_image');
        }
    }

    public function formatPrice(float $price): string
    {
        return $this->priceHelper->currency($price, true, false);
    }

    public function getDiscountPercent(\Magento\Catalog\Model\Product $product): int
    {
        $regular = (float) $product->getPrice();
        $final = $this->getEffectiveFinalPrice($product);
        if ($regular <= 0 || $final <= 0 || $final >= $regular) {
            return 0;
        }

        return (int) round((($regular - $final) / $regular) * 100);
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getCurrentSort(): string
    {
        $sort = (string) $this->getRequest()->getParam('sort', 'discount_desc');
        $allowed = ['discount_desc', 'discount_asc', 'price_asc', 'price_desc', 'newest'];

        return in_array($sort, $allowed, true) ? $sort : 'discount_desc';
    }

    public function getCurrentLimit(): int
    {
        return 10;
    }

    public function getEffectiveFinalPrice(\Magento\Catalog\Model\Product $product): float
    {
        $id = (int) $product->getId();
        if (isset($this->fallbackOfferPriceMap[$id])) {
            return (float) $this->fallbackOfferPriceMap[$id];
        }

        $providerPrice = $this->offerPriceProvider->getDiscountedPriceForProduct($product);
        if ($providerPrice !== null) {
            return $providerPrice;
        }

        return (float) $product->getFinalPrice();
    }

    private function buildFallbackOfferCollection(int $storeId, array $selectedCategoryIds): Collection
    {
        $discountMap = $this->offerPriceProvider->getOfferProductDiscountMap($storeId);
        $pickedIds = array_keys($discountMap);
        if ($selectedCategoryIds !== []) {
            $filterCollection = $this->productCollectionFactory->create();
            $filterCollection->setStoreId($storeId);
            $filterCollection->addStoreFilter($storeId);
            $filterCollection->addAttributeToSelect(['entity_id']);
            $filterCollection->addCategoriesFilter(['in' => $selectedCategoryIds]);
            $filterCollection->addIdFilter($pickedIds !== [] ? $pickedIds : [0]);
            $pickedIds = array_map(static fn($p): int => (int) $p->getId(), $filterCollection->getItems());
        }

        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect(['name', 'small_image', 'price', 'final_price', 'special_price', 'created_at']);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->productVisibility->getVisibleInCatalogIds());
        $this->reviewSummaryResource->appendSummaryFieldsToCollection($collection, $storeId, 'product');
        $collection->addIdFilter($pickedIds !== [] ? $pickedIds : [0]);
        $collection->getSelect()->order('e.entity_id DESC');

        return $collection;
    }
}
