<?php
declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;

require __DIR__ . '/../../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

/** @var State $state */
$state = $objectManager->get(State::class);
try {
    $state->setAreaCode('adminhtml');
} catch (\Throwable $e) {
    // Area code might be already set.
}

/** @var StoreManagerInterface $storeManager */
$storeManager = $objectManager->get(StoreManagerInterface::class);
/** @var ProductRepositoryInterface $productRepo */
$productRepo = $objectManager->get(ProductRepositoryInterface::class);
/** @var ProductFactory $productFactory */
$productFactory = $objectManager->get(ProductFactory::class);
/** @var CategoryCollectionFactory $categoryCollectionFactory */
$categoryCollectionFactory = $objectManager->get(CategoryCollectionFactory::class);

$sku = 'ttk-laptop-demo-01';
try {
    $productRepo->get($sku);
    echo "Product already exists\n";
    exit(0);
} catch (\Throwable $e) {
    // Continue to create product.
}

$categoryCollection = $categoryCollectionFactory->create();
$categoryCollection->addAttributeToSelect(['url_key']);
$categoryCollection->addAttributeToFilter('url_key', 'laptops');
$category = $categoryCollection->getFirstItem();
$categoryIds = $category && $category->getId() ? [(int) $category->getId()] : [2];

$product = $productFactory->create();
$product->setSku($sku);
$product->setName('HP Laptop with Intel Core i7');
$product->setAttributeSetId(4);
$product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
$product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
$product->setTypeId('simple');
$product->setPrice(1099);
$product->setUrlKey('hp-laptop-intel-core-i7');
$product->setCategoryIds($categoryIds);
$product->setStockData(['use_config_manage_stock' => 1, 'qty' => 25, 'is_in_stock' => 1]);
$product->setShortDescription('Intel Core i7, 16GB RAM, 512GB SSD, 15.6" Full HD display.');
$product->setDescription(
    'Sleek performance laptop with Intel Core i7, fast NVMe storage, and an IPS display. ' .
    'Perfect for work, study, and everyday productivity with long battery life.'
);

$store = $storeManager->getStore();
$product->setWebsiteIds([(int) $store->getWebsiteId()]);

$mediaDir = BP . '/pub/media/catalog/product';
$imageSources = [
    BP . '/pub/media/laptop.png',
    BP . '/pub/media/monitor.png',
    BP . '/pub/media/keyboard.png',
    BP . '/pub/media/mouse.png',
];

foreach ($imageSources as $path) {
    if (is_file($path)) {
        $product->addImageToMediaGallery($path, ['image', 'small_image', 'thumbnail'], false, false);
    }
}

$productRepo->save($product);

// Set stock via MSI if available.
try {
    /** @var SourceItemInterfaceFactory $sourceItemFactory */
    $sourceItemFactory = $objectManager->get(SourceItemInterfaceFactory::class);
    /** @var SourceItemsSaveInterface $sourceItemsSave */
    $sourceItemsSave = $objectManager->get(SourceItemsSaveInterface::class);
    $sourceItem = $sourceItemFactory->create();
    $sourceItem->setSourceCode('default');
    $sourceItem->setSku($sku);
    $sourceItem->setQuantity(25);
    $sourceItem->setStatus(1);
    $sourceItemsSave->execute([$sourceItem]);
} catch (\Throwable $e) {
    // Ignore if MSI isn't available.
    try {
        /** @var StockRegistryInterface $stockRegistry */
        $stockRegistry = $objectManager->get(StockRegistryInterface::class);
        $stockItem = $stockRegistry->getStockItemBySku($sku);
        $stockItem->setQty(25);
        $stockItem->setIsInStock(true);
        $stockRegistry->updateStockItemBySku($sku, $stockItem);
    } catch (\Throwable $e2) {
        // Ignore stock update failures.
    }
}

echo "Created sample product\n";
