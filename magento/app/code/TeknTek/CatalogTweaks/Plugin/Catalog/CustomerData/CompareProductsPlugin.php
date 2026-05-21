<?php

declare(strict_types=1);

namespace TeknTek\CatalogTweaks\Plugin\Catalog\CustomerData;

use Magento\Catalog\CustomerData\CompareProducts;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;

class CompareProductsPlugin
{
    public function __construct(
        private readonly CollectionFactory $productCollectionFactory,
        private readonly ImageHelper $imageHelper,
        private readonly MediaConfig $mediaConfig,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Add display data used by the compact customer sidebar.
     *
     * Magento's default compare customer-data only contains id, name, URL and
     * remove payload. The sidebar needs thumbnail + formatted price, so enrich
     * the section once here instead of doing per-item work in the template.
     */
    public function afterGetSectionData(CompareProducts $subject, array $sectionData): array
    {
        $items = $sectionData['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return $sectionData;
        }

        $ids = [];
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return $sectionData;
        }

        $products = $this->loadProducts(array_values($ids));
        foreach ($items as $index => $item) {
            $id = (int) ($item['id'] ?? 0);
            if (!isset($products[$id])) {
                continue;
            }

            $product = $products[$id];
            $items[$index]['image'] = $this->getImageData($product);
            $items[$index]['price'] = $this->priceCurrency->format(
                (float) $product->getFinalPrice(),
                false,
                PriceCurrencyInterface::DEFAULT_PRECISION,
                (int) $this->storeManager->getStore()->getId()
            );
        }

        $sectionData['items'] = $items;

        return $sectionData;
    }

    /**
     * @param int[] $ids
     * @return array<int, Product>
     */
    private function loadProducts(array $ids): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId((int) $this->storeManager->getStore()->getId());
        $collection->addAttributeToSelect(['name', 'image', 'small_image', 'thumbnail', 'price', 'special_price']);
        $collection->addFieldToFilter('entity_id', ['in' => $ids]);
        $collection->load();

        $products = [];
        foreach ($collection as $product) {
            if ($product instanceof Product) {
                $products[(int) $product->getId()] = $product;
            }
        }

        return $products;
    }

    private function getProductImageUrl(Product $product): string
    {
        $image = (string) $product->getImage();
        if ($image !== '' && $image !== 'no_selection') {
            return $this->mediaConfig->getMediaUrl($image);
        }

        $smallImage = (string) $product->getSmallImage();
        if ($smallImage !== '' && $smallImage !== 'no_selection') {
            return $this->mediaConfig->getMediaUrl($smallImage);
        }

        $thumbnail = (string) $product->getThumbnail();
        if ($thumbnail !== '' && $thumbnail !== 'no_selection') {
            return $this->mediaConfig->getMediaUrl($thumbnail);
        }

        return $this->imageHelper->init($product, 'product_thumbnail_image')->getUrl();
    }

    /**
     * Match Magento wishlist customer-data image shape so Knockout can render
     * the same image template in compact sidebar blocks.
     *
     * @return array{template:string,src:string,width:int,height:int,alt:string}
     */
    private function getImageData(Product $product): array
    {
        $helper = $this->imageHelper->init($product, 'wishlist_sidebar_block');
        $src = (string) $helper->getUrl();

        return [
            'template' => 'Magento_Catalog/product/image_with_borders',
            'src' => $src,
            'width' => (int) ($helper->getWidth() ?: 80),
            'height' => (int) ($helper->getHeight() ?: 80),
            'alt' => (string) ($helper->getLabel() ?: $product->getName()),
        ];
    }
}
