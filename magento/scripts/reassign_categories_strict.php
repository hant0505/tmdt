<?php
require __DIR__ . '/../app/bootstrap.php';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$state = $om->get(\Magento\Framework\App\State::class);
try { $state->setAreaCode('adminhtml'); } catch (Exception $e) {}

$resource = $om->get(\Magento\Framework\App\ResourceConnection::class);
$conn = $resource->getConnection();
$categoryCollectionFactory = $om->get(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class);
$productCollectionFactory = $om->get(\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory::class);

$categories = $categoryCollectionFactory->create();
$categories->addAttributeToSelect(['name', 'url_key']);
$categories->addAttributeToFilter('parent_id', ['eq' => 3]);

$map = [];
foreach ($categories as $c) {
    $map[(string)$c->getUrlKey()] = (int)$c->getId();
}

$priority = [
    'computer-mice' => ['/\bmouse\b/', '/\bmice\b/'],
    'keyboards' => ['/\bkeyboard\b/'],
    'hard-drives' => ['/\bssd\b/', '/\bhdd\b/', '/\bhard\s*drive\b/', '/\bnvme\b/', '/\bexternal\s*drive\b/'],
    'audio-headphones' => ['/\bheadphone\b/', '/\bearbud\b/', '/\bearphone\b/', '/\bspeaker\b/', '/\bheadset\b/'],
    'smart-watches' => ['/\bsmart\s?watch\b/', '/\bsmartwatch\b/'],
    'cameras' => ['/\bcamera\b/', '/\bdslr\b/', '/\bmirrorless\b/', '/\baction\s?cam\b/'],
    'monitors' => ['/\bmonitor\b/', '/\bled\s+monitor\b/', '/\bips\s+monitor\b/'],
    'tablets' => ['/\btablet\b/', '/\bipad\b/'],
    'smartphones' => ['/\bsmartphone\b/', '/\biphone\b/', '/\bmobile\s?phone\b/', '/\bandroid\s?phone\b/', '/\bsamsung\s+galaxy\b/', '/\bgalaxy\s+s\d+/'],
    'laptops' => ['/\blaptop\b/', '/\bnotebook\b/', '/\bmacbook\b/', '/\bchromebook\b/', '/\bcomputer\b/']
];

$targetCategoryIds = [];
foreach (array_keys($priority) as $slug) {
    if (isset($map[$slug])) {
        $targetCategoryIds[] = (int)$map[$slug];
    }
}

if (empty($targetCategoryIds)) {
    echo "No target categories found under Imported Products.\n";
    exit(1);
}

$productCollection = $productCollectionFactory->create();
$productCollection->addAttributeToSelect(['name', 'short_description']);

$rows = [];
$assigned = 0;
$skipped = 0;

foreach ($productCollection as $p) {
    $name = strtolower(trim((string)$p->getName()));
    $short = strtolower(trim((string)$p->getShortDescription()));
    $haystack = trim($name . ' ' . $short);
    $target = null;

    foreach ($priority as $slug => $patterns) {
        if (!isset($map[$slug])) {
            continue;
        }

        $hit = false;
        foreach ($patterns as $rx) {
            if (preg_match($rx, $haystack)) {
                $hit = true;
                break;
            }
        }

        // Guard against smart-watch descriptions like "heart rate monitor".
        if ($hit && $slug === 'monitors' && preg_match('/\bwatch\b|\bsmart\s?watch\b|fitness\s+tracker/', $haystack)) {
            $hit = false;
        }

        if ($hit) {
            $target = $slug;
            break;
        }
    }

    if (!$target) {
        $skipped++;
        continue;
    }

    $rows[] = [
        'category_id' => (int)$map[$target],
        'product_id' => (int)$p->getId(),
        'position' => 0
    ];
    $assigned++;
}

$table = $resource->getTableName('catalog_category_product');
$conn->beginTransaction();
try {
    $conn->delete($table, ['category_id IN (?)' => $targetCategoryIds]);
    if (!empty($rows)) {
        $conn->insertMultiple($table, $rows);
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollBack();
    throw $e;
}

echo "Assigned rows: {$assigned}\n";
echo "Skipped products: {$skipped}\n";
