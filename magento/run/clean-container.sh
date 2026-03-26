rm -rf var/cache/* var/page_cache/* generated/*

php -d memory_limit=-1 bin/magento setup:upgrade
php -d memory_limit=-1 bin/magento setup:di:compile
php bin/magento cache:clean
php bin/magento cache:flush