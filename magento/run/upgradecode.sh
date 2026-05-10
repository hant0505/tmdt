#!/bin/bash

# Upgrade all module
php -d memory_limit=-1 bin/magento setup:upgrade

# Rebuild static css
php -d memory_limit=-1 bin/magento setup:static-content:deploy -f

# Cache flush
php bin/magento cache:flush