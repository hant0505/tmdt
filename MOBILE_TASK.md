# MOBILE_TASK.md

## Mục tiêu

Tối ưu giao diện mobile cho Magento project, ưu tiên sửa các vấn đề responsive như tràn ngang, layout bị lệch, nút/form quá rộng, khoảng cách không hợp lý.

## Quy tắc sửa code

* Project Magento chạy bằng Docker, nhưng Codex không cần chạy Docker.
* Chỉ sửa source code cần thiết.
* Ưu tiên sửa CSS/LESS trước.
* Chỉ sửa template HTML/PHTML nếu CSS/LESS không đủ xử lý.
* Không sửa logic PHP/JS nếu không bắt buộc.
* Không rewrite toàn bộ file.
* Không đổi class name nếu class đó đang được Magento/Knockout/JS sử dụng.
* Không làm ảnh hưởng desktop layout.
* Ưu tiên đặt CSS mobile trong `@media (max-width: 767px)`.

Các màn hình ưu tiên:
1. Header / menu / minicart
2. Product listing
3. Product detail
4. Cart
5. Checkout
6. Login/Register

## Không đọc/sửa các thư mục sau

* `vendor/`
* `pub/static/`
* `var/`
* `generated/`
* `node_modules/`
* `.git/`

## Breakpoint

* Mobile: `max-width: 767px`
* Tablet: `768px - 1024px`
* Desktop: giữ nguyên nếu có thể

## File CSS/LESS chính liên quan giao diện

### Theme chính (TeknTek/OrangeTheme)
- magento/app/design/frontend/TeknTek/OrangeTheme/web/css/source/_extend.less
- magento/app/design/frontend/TeknTek/OrangeTheme/web/css/source/_homepage.less
- magento/app/design/frontend/TeknTek/OrangeTheme/web/css/source/_variables.less
- magento/app/design/frontend/TeknTek/OrangeTheme/web/css/tekntek-addtocart.css
- magento/app/design/frontend/TeknTek/OrangeTheme/web/css/tekntek-listing-modern.css

### Checkout
- magento/app/design/frontend/TeknTek/OrangeTheme/Magento_Checkout/web/css/source/_coupon-modal.less
- magento/app/design/frontend/TeknTek/OrangeTheme/Magento_Checkout/web/css/source/_extend.less

### Customer (Account)
- magento/app/design/frontend/TeknTek/OrangeTheme/Magento_Customer/web/css/account-dashboard.css
- magento/app/design/frontend/TeknTek/OrangeTheme/Magento_Customer/web/css/account-dashboard-modern.css
- magento/app/design/frontend/TeknTek/OrangeTheme/Magento_Customer/web/css/account-order-fixes.css
- magento/app/design/frontend/TeknTek/OrangeTheme/Magento_Customer/web/css/address-layout-fix.css

### Sales & Review
- magento/app/design/frontend/TeknTek/OrangeTheme/Magento_Sales/web/css/order-history-empty.css
- magento/app/design/frontend/TeknTek/OrangeTheme/Magento_Review/web/css/customer-reviews.css

### Widgets
- magento/app/design/frontend/TeknTek/OrangeTheme/Vendor_BusinessNews/web/css/businessnews.css
- magento/app/design/frontend/TeknTek/OrangeTheme/Vendor_Currency/web/css/currency-widget.css
- magento/app/design/frontend/TeknTek/OrangeTheme/Vendor_Weather/web/css/weather-widget.css

## Cách phản hồi

Mỗi lần sửa xong cần liệt kê:

* Đã sửa file nào
* Sửa gì
* Vì sao sửa
* Có ảnh hưởng desktop không
* Tôi cần tự chạy lệnh Magento/Docker nào để kiểm tra

