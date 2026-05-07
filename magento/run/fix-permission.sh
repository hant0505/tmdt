#!/bin/bash
echo "=== Starting Magento 2 Permission Fix ==="

echo "Removing old cache and session files..."
docker exec -it magento_php bash -c "
    rm -rf /var/www/html/var/cache/* \
           /var/www/html/var/page_cache/* \
           /var/www/html/var/session/* \
           /var/www/html/generated/* 2>/dev/null || true
"

echo "Fixing permissions inside Docker container..."
docker exec -it magento_php bash -c '
    cd /var/www/html
    
    # Đổi owner thành www-data
    chown -R www-data:www-data var/ generated/ pub/static/ pub/media/ app/etc/ 2>/dev/null || true
    
    # Thư mục writable = 775
    find var generated pub/static pub/media app/etc -type d -exec chmod 775 {} + 2>/dev/null || true
    
    # File = 664
    find var generated pub/static pub/media app/etc -type f -exec chmod 664 {} + 2>/dev/null || true
    
    echo "Permission fixed INSIDE container."
'

echo "Fixing permissions on Host..."


sudo chown -R punpun:www-data .
sudo find var generated pub/static pub/media app/etc -type d -exec chmod 775 {} + 2>/dev/null || true
sudo find var generated pub/static pub/media app/etc -type f -exec chmod 664 {} + 2>/dev/null || true

echo "=== Permission Fix Completed Successfully ==="
