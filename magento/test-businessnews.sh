#!/bin/bash
# Script test module BusinessNews

echo "=== Testing Module BusinessNews ==="
echo ""

# 1. Check if module is enabled
echo "1. Checking if module is enabled..."
docker exec -it magento_php bash -c "cd /var/www/html && php bin/magento module:status | grep -i businessnews"
echo ""

# 2. Check if routes are registered
echo "2. Checking routes..."
docker exec -it magento_php bash -c "cd /var/www/html && php bin/magento cache:clean && echo 'Cache cleaned'"
echo ""

# 3. Test RSS connection
echo "3. Testing VNExpress RSS connection..."
docker exec -it magento_php bash -c "
php -r \"
try {
    \\\$xml = simplexml_load_file('https://vnexpress.net/rss/kinh-doanh.rss', 'SimpleXMLElement', LIBXML_NOCDATA);
    if (\\\$xml && isset(\\\$xml->channel)) {
        \\\$count = count(\\\$xml->channel->item);
        echo 'RSS Connection: OK' . PHP_EOL;
        echo 'Items in feed: ' . \\\$count . PHP_EOL;
        
        echo PHP_EOL . 'First 3 items:' . PHP_EOL;
        \\\$i = 0;
        foreach (\\\$xml->channel->item as \\\$item) {
            if (\\\$i >= 3) break;
            echo '  - ' . (string)\\\$item->title . PHP_EOL;
            \\\$i++;
        }
    } else {
        echo 'RSS Connection: FAILED' . PHP_EOL;
    }
} catch (Exception \\\$e) {
    echo 'Error: ' . \\\$e->getMessage() . PHP_EOL;
}
\"
"
echo ""

# 4. Deploy static files
echo "4. Deploying static files..."
docker exec -it magento_php bash -c "cd /var/www/html && php bin/magento setup:static-content:deploy -f"
echo ""

# 5. Clear all caches
echo "5. Clearing all caches..."
docker exec -it magento_php bash -c "cd /var/www/html && php bin/magento cache:flush"
echo ""

echo "=== Test Complete ==="
echo "Access the page at: http://localhost/businessnews/"
echo ""
