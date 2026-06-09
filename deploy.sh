#!/bin/bash

echo "--- 1. Resetting permissions inside container ---"
docker exec -u root magento_php chown -R www-data:www-data /var/www/html
docker exec -u root magento_php chmod -R 775 /var/www/html

echo "--- 2. Running setup:upgrade ---"
docker exec -u www-data magento_php php -d memory_limit=-1 bin/magento setup:upgrade

echo "--- 3. Clearing static and generated cache ---"
docker exec -u root magento_php rm -rf pub/static/adminhtml/* pub/static/frontend/* var/view_preprocessed/* generated/code/*

echo "--- 4. Deploying static content ---"
docker exec -u www-data magento_php php -d memory_limit=-1 bin/magento setup:static-content:deploy -f

echo "--- 5. Flushing Magento cache ---"
docker exec -u www-data magento_php php bin/magento cache:flush

