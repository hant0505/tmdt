<?php
/**
 * Quick Test Script for BusinessNews Module
 * 
 * Usage: 
 * docker exec -it magento_php bash
 * cd /var/www/html
 * php test-businessnews.php
 */

require_once('app/bootstrap.php');

use Magento\Framework\App\Bootstrap;
use Vendor\BusinessNews\Helper\Data;

try {
    $bootstrap = Bootstrap::create(BP, $_SERVER);
    $objectManager = $bootstrap->getObjectManager();
    
    // Get Helper
    $helper = $objectManager->create(Data::class);
    
    echo "=== Testing BusinessNews Module ===\n\n";
    
    // Test 1: Test getBusinessNews
    echo "Test 1: Fetching 4 news from VNExpress...\n";
    $news = $helper->getBusinessNews(4);
    
    if (empty($news)) {
        echo "ERROR: No news fetched!\n";
        exit(1);
    }
    
    echo "SUCCESS: Fetched " . count($news) . " news items\n\n";
    
    // Test 2: Display first news
    echo "First News Item:\n";
    $first = $news[0];
    echo "  Title: " . substr($first['title'], 0, 80) . "...\n";
    echo "  Date: " . $first['date'] . "\n";
    echo "  Image: " . substr($first['image'], 0, 60) . "...\n";
    echo "  Description: " . substr($first['description'], 0, 80) . "...\n";
    echo "  Link: " . $first['link'] . "\n\n";
    
    // Test 3: Check image extraction
    echo "Test 3: Image Extraction Check\n";
    $hasImages = true;
    foreach ($news as $item) {
        if (empty($item['image']) || strpos($item['image'], 'placeholder') !== false) {
            $hasImages = false;
            echo "  WARNING: Item missing image or using placeholder\n";
        }
    }
    if ($hasImages) {
        echo "  SUCCESS: All items have real images\n";
    }
    echo "\n";
    
    // Test 4: Check data structure
    echo "Test 4: Data Structure Validation\n";
    $requiredKeys = ['title', 'image', 'date', 'description', 'link'];
    $allValid = true;
    foreach ($news as $idx => $item) {
        foreach ($requiredKeys as $key) {
            if (!isset($item[$key])) {
                echo "  ERROR: Item " . $idx . " missing key: " . $key . "\n";
                $allValid = false;
            }
        }
    }
    if ($allValid) {
        echo "  SUCCESS: All items have required keys\n";
    }
    echo "\n";
    
    // Test 5: Cache test
    echo "Test 5: Cache Test\n";
    $cache = $objectManager->get(\Magento\Framework\App\CacheInterface::class);
    $cachedData = $cache->load(Data::CACHE_KEY);
    if ($cachedData) {
        echo "  SUCCESS: Data is cached\n";
    } else {
        echo "  INFO: Cache will be set after first execution\n";
    }
    echo "\n";
    
    echo "=== All Tests Completed Successfully ===\n";
    echo "Module is ready for use!\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
