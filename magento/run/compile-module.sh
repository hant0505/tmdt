#!/bin/bash
# rm -rf var/cache/* var/page_cache/* var/generation/* generated/*

php -d memory_limit=-1 bin/magento setup:upgrade
php -d memory_limit=-1 bin/magento setup:di:compile
php -d memory_limit=-1 bin/magento setup:static-content:deploy -f
php bin/magento cache:clean
php bin/magento cache:flush