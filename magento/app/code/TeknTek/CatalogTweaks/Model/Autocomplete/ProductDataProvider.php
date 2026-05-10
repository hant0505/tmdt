<?php

declare(strict_types=1);

namespace TeknTek\CatalogTweaks\Model\Autocomplete;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogSearch\Model\Autocomplete\DataProvider as CoreDataProvider;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Search\Model\Autocomplete\DataProviderInterface;
use Magento\Search\Model\Autocomplete\ItemFactory;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProductDataProvider implements DataProviderInterface
{
    public function __construct(
        private readonly QueryFactory $queryFactory,
        private readonly ItemFactory $itemFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly StoreManagerInterface $storeManager,
        private readonly Visibility $visibility,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getItems(): array
    {
        $query = trim((string) $this->queryFactory->get()->getQueryText());
        if ($query === '') {
            return [];
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $limit = max(1, (int) $this->scopeConfig->getValue(
            CoreDataProvider::CONFIG_AUTOCOMPLETE_LIMIT,
            ScopeInterface::SCOPE_STORE
        ));

        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->setVisibility($this->visibility->getVisibleInSearchIds());
        $collection->addSearchFilter($query);
        $collection->addAttributeToSelect(['name', 'small_image', 'thumbnail', 'image', 'price', 'special_price']);
        $collection->addMinimalPrice();
        $collection->addFinalPrice();
        $collection->addUrlRewrite();
        $collection->setPageSize($limit);
        $collection->setCurPage(1);

        $items = [];
        foreach ($collection as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $regularAmount = (float) $product->getPrice();
            $finalAmount = (float) $product->getFinalPrice();
            if ($regularAmount <= 0) {
                $regularAmount = $finalAmount;
            }

            $items[] = $this->itemFactory->create([
                'title' => (string) $product->getName(),
                'product_url' => (string) $product->getProductUrl(),
                'product_image' => (string) $this->imageHelper->init($product, 'product_small_image')->getUrl(),
                'regular_price' => $this->formatPrice($regularAmount, $storeId),
                'final_price' => $this->formatPrice($finalAmount, $storeId),
                'regular_price_amount' => $regularAmount,
                'final_price_amount' => $finalAmount,
                'has_discount' => $regularAmount > $finalAmount,
                'num_results' => null,
                'type' => 'product',
            ]);
        }

        return $items;
    }

    private function formatPrice(float $amount, int $storeId): string
    {
        return $this->priceCurrency->format(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            $storeId
        );
    }
}
