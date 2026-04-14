<?php
declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\State;

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

/** @var CategoryFactory $categoryFactory */
$categoryFactory = $objectManager->get(CategoryFactory::class);
/** @var CategoryRepositoryInterface $categoryRepository */
$categoryRepository = $objectManager->get(CategoryRepositoryInterface::class);
/** @var CategoryCollectionFactory $categoryCollectionFactory */
$categoryCollectionFactory = $objectManager->get(CategoryCollectionFactory::class);

$parentId = 2; // Default Category
$parent = $categoryRepository->get($parentId, 0);

$categories = [
    ['name' => 'Hard Drives', 'url_key' => 'hard-drives'],
    ['name' => 'Computer Mice', 'url_key' => 'computer-mice'],
    ['name' => 'Keyboards', 'url_key' => 'keyboards'],
    ['name' => 'Monitors', 'url_key' => 'monitors'],
    ['name' => 'Laptops', 'url_key' => 'laptops'],
    ['name' => 'Smartphones', 'url_key' => 'smartphones'],
    ['name' => 'Cameras', 'url_key' => 'cameras'],
    ['name' => 'Smart Watches', 'url_key' => 'smart-watches'],
    ['name' => 'Audio & Headphones', 'url_key' => 'audio-headphones'],
    ['name' => 'Tablets', 'url_key' => 'tablets'],
];

foreach ($categories as $data) {
    $collection = $categoryCollectionFactory->create();
    $collection->addAttributeToSelect(['name', 'url_key']);
    $collection->addAttributeToFilter('url_key', $data['url_key']);
    $collection->setPageSize(1);
    $existing = $collection->getFirstItem();

    if ($existing && $existing->getId()) {
        continue;
    }

    $category = $categoryFactory->create();
    $category->setName($data['name']);
    $category->setIsActive(1);
    $category->setIncludeInMenu(1);
    $category->setIsAnchor(1);
    $category->setParentId($parentId);
    $category->setPath($parent->getPath());
    $category->setUrlKey($data['url_key']);
    $categoryRepository->save($category);
}

echo "Done\n";
