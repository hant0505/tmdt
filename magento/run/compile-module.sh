#!/bin/bash
# rm -rf var/cache/* var/page_cache/* var/generation/* generated/*

php -d memory_limit=-1 bin/magento setup:upgrade
php -d memory_limit=-1 bin/magento setup:di:compile
php -d memory_limit=-1 bin/magento setup:static-content:deploy -f
php bin/magento cache:clean
php bin/magento cache:flush

rm -rf /var/www/html/var/cache/* \
           /var/www/html/var/page_cache/* \
           /var/www/html/generated/* 2>/dev/null || true

chown -R www-data:www-data /var/www/html
chmod -R 775 var pub/static pub/media generated app/etc

chown -R www-data:www-data var/ generated/ pub/static/ pub/media/ app/etc/ 2>/dev/null || true
    
find var generated pub/static pub/media app/etc -type d -exec chmod 775 {} + 2>/dev/null || true
    
find var generated pub/static pub/media app/etc -type f -exec chmod 664 {} + 2>/dev/null || true
