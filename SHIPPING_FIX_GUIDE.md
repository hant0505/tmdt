# 🔧 HƯỚNG DẪN SỬ CHỮA CHI PHÍ VẬN CHUYỂN $0.00 - GHN/GHTK

## ✅ ĐÃ HOÀN THÀNH CÁC THAY ĐỔI

Tôi đã sửa chữa toàn bộ lỗi trong code của bạn:

### 📝 Tệp đã sửa:
1. ✅ [magento/app/code/Shipping/GHTK/Model/Carrier/Ghtk.php](magento/app/code/Shipping/GHTK/Model/Carrier/Ghtk.php)
   - ✓ Khắc phục lỗi tỷ giá hối đoái (Exchange rate)
   - ✓ Thêm chuyển đổi đơn vị trọng lượng (Weight unit conversion)
   - ✓ Thêm fallback rate an toàn

2. ✅ [magento/app/code/Shipping/GHN/Model/Carrier/Ghn.php](magento/app/code/Shipping/GHN/Model/Carrier/Ghn.php)
   - ✓ Khắc phục lỗi tỷ giá hối đoái (Exchange rate)
   - ✓ Thêm chuyển đổi đơn vị trọng lượng (Weight unit conversion)
   - ✓ Cho phép cấu hình `to_district_id` từ admin (thay vì hardcoded)

3. ✅ [magento/app/code/Shipping/GHN/etc/adminhtml/system.xml](magento/app/code/Shipping/GHN/etc/adminhtml/system.xml)
   - ✓ Thêm trường `from_district_id` trong admin
   - ✓ Thêm trường `to_district_id` trong admin

---

## 🚀 HƯỚNG DẪN TỰ ĐỘNG ĐẦU CUỐI (Tự động)

### Bước 1: Xóa Cache Magento
Chạy lệnh để xóa cache và compiled code:
```bash
cd /home/tmdt/tmdt/magento
rm -rf var/cache/*
rm -rf var/page_cache/*
rm -rf generated/code/*
php bin/magento cache:clean
php bin/magento cache:flush
```

### Bước 2: Xóa Cache Static Files (Optional nhưng khuyến nghị)
```bash
rm -rf pub/static/*
php bin/magento setup:static-content:deploy
```

### Bước 3: Kiểm Tra Cấu Hình Tỷ Giá
Truy cập Admin Magento:
1. Vào **Stores → Settings → Configuration → General → Currency Setup**
2. Bấm **Import** để cập nhật tỷ giá VND ↔ USD
3. Hoặc cấu hình manual tỷ giá (1 USD ≈ 25,000-26,000 VND)

### Bước 4: Kiểm Tra Weight Unit
Vào **Stores → Settings → Configuration → General → Locale Options**
- Kiểm tra trường **Weight Unit**
- Ghi nhớ giá trị (KG hoặc LBS)

### Bước 5: Cấu Hình GHN/GHTK trong Admin
Vào **Stores → Configuration → Sales → Shipping Methods**

#### GHN (Giao Hàng Nhanh):
```
✓ Enabled: Yes
✓ Title: Giao Hàng Nhanh
✓ API Token: [Token thật từ GHN]
✓ Shop ID: [Shop ID thật từ GHN]
✓ From District ID: 3440 (Cầu Giấy, Hà Nội) hoặc quận gửi của bạn
✓ To District ID: 3440 (mặc định nhận hàng)
```

#### GHTK (Giao Hàng Tiết Kiệm):
```
✓ Enabled: Yes
✓ Title: Giao Hàng Tiết Kiệm
✓ API Token: [Token từ GHTK]
✓ Sandbox Mode: No (tắt sau khi test xong)
```

### Bước 6: Test Shipping Calculation
1. Tạo hoặc sửa một sản phẩm test với **weight > 0**
2. Thêm vào giỏ hàng
3. Tiến hành Checkout → chọn Shipping Method
4. Kiểm tra xem giá được tính chính xác chưa

---

## 🔍 KIỂM TRA LỖI - DEBUG

### Log Files
Kiểm tra file log để xem debug info:
```bash
tail -f /home/tmdt/tmdt/magento/var/log/system.log | grep -E "GHN|GHTK|Currency"
```

### Những tin nhắn log quan trọng:
- ✅ "Weight conversion: X lbs → Y kg" → Weight chuyển đổi thành công
- ✅ "Exchange rate VND/USD not found, using fallback 26310" → Dùng tỷ giá fallback
- ❌ "API Error" hoặc "API Exception" → Có vấn đề với API credentials
- ❌ "Thiếu API Token" → Chưa nhập API token trong admin

### Enable Debug Mode (Optional)
Tạo file `/home/tmdt/tmdt/magento/app/etc/config.local.php`:
```php
<?php
return [
    'system' => [
        'default' => [
            'debug' => [
                'profiler' => [
                    'enabled' => 1
                ]
            ]
        ]
    ]
];
```

---

## 📊 EXPECTED RESULTS (Kỳ Vọng)

Sau khi áp dụng fix:

| Trước | Sau |
|-------|-----|
| GHN Express: **$0.00** | GHN Express: **$1.50** (ví dụ) |
| GHN Standard: **$0.00** | GHN Standard: **$1.20** (ví dụ) |
| GHTK Tiết kiệm: **$0.00** | GHTK Tiết kiệm: **$1.00** (ví dụ) |
| GHTK Hỏa tốc: **$0.00** | GHTK Hỏa tốc: **$1.50** (ví dụ) |

> Lưu ý: Giá cụ thể phụ thuộc vào weight sản phẩm, destination, và tỷ giá VND/USD

---

## 🐛 NẾUU VẪN LỖI - Troubleshooting

### Lỗi 1: Vẫn Hiển Thị $0.00

**Nguyên nhân tiềm ẩn:**
- [ ] Chưa xóa cache Magento
- [ ] Tỷ giá VND/USD chưa được import (kiểm tra Admin → Currency Setup)
- [ ] API credentials sai (token hoặc shop id)
- [ ] Sản phẩm không có weight

**Cách khắc phục:**
```bash
# 1. Xóa cache hoàn toàn
php bin/magento cache:clean
php bin/magento cache:flush

# 2. Tạo sản phẩm test với weight = 1 KG
# 3. Kiểm tra system.log:
grep "GHTK\|GHN" var/log/system.log
```

### Lỗi 2: Giá Quá Cao hoặc Quá Thấp

**Nguyên nhân:**
- Tỷ giá hối đoái không chính xác (ví dụ 1 USD = 1 VND)
- Weight unit nhầm lẫn (LBS vs KG)

**Cách khắc phục:**
```bash
# Kiểm tra weight unit:
php -r "echo file_get_contents('magento/app/etc/env.php');"

# Hoặc vào Admin kiểm tra: Stores → Configuration → Locale Options
```

### Lỗi 3: API Exception - "Thiếu Token"

**Nguyên nhân:** Chưa cấu hình API token trong admin

**Cách khắc phục:**
1. Vào Admin → Stores → Configuration → Sales → Shipping Methods → GHN/GHTK
2. Nhập API Token/Shop ID đúng
3. Lưu lại → Clear cache

---

## 💾 CÁC THAY ĐỔI CHI TIẾT

### A. GHTK Module - Ghtk.php

**Thay đổi 1:** Weight Unit Conversion
```php
// Trước:
$weightInKg = $request->getPackageWeight() ?: 1;

// Sau:
$weight = $request->getPackageWeight() ?: 1;
$weightInKg = $this->_convertWeightToKg($weight);
```

**Thay đổi 2:** Currency Exchange Rate Safe Check
```php
// Trước:
return round($amountVND * $rate, 4); // Nếu $rate = NULL → = 0

// Sau:
if ($rate && $rate > 0) {
    return round($amountVND * $rate, 4);
}
// Fallback rate
return round($amountVND / 26310, 4);
```

**Thay đổi 3:** Weight Conversion Method
```php
// Thêm hàm mới:
private function _convertWeightToKg(float $weight): float
{
    // Lấy weight unit từ Magento config
    // Nếu LBS → chuyển sang KG (1 LB = 0.453592 KG)
    // Nếu KG → giữ nguyên
}
```

---

### B. GHN Module - Ghn.php

**Giống GHTK thêm weight unit conversion + currency fix**

**Thay đổi bổ sung:** District ID Configuration
```php
// Trước:
private function getToDistrictId()
{
    return 3440; // Hardcoded
}

// Sau:
private function getToDistrictId()
{
    $districtId = (int)$this->getConfigData('to_district_id');
    if (!$districtId) {
        $districtId = 3440; // Mặc định
    }
    return $districtId;
}
```

---

## 📞 LIÊN HỆ/KIỂM TRA

Nếu vẫn gặp vấn đề, kiểm tra:

1. **System Log:** `var/log/system.log`
2. **API Response:** Thêm debug log vào `_getGhtkFeeFromApi()` / `_getGhnFeeFromApi()`
3. **Database:** Kiểm tra currency rates được import chưa
4. **Admin Config:** Đảm bảo tất cả cấu hình đúng

---

## ✨ TÓM LẠI

**Nguyên nhân chính:** 
- 🔴 **Exchange rate NULL** → Shipping cost = 0
- 🔴 **Weight unit sai** → Giá sai (tuy nhiên không phải 0)
- 🔴 **District ID hardcoded** → Không có route → Fallback cost

**Giải pháp:**
- ✅ Thêm safe check cho exchange rate
- ✅ Tự động chuyển đổi weight unit (LBS ↔ KG)
- ✅ Cho phép cấu hình district ID từ admin

**Sau fix, bạn sẽ thấy:**
- ✅ Shipping cost hiển thị đúng (không phải $0.00)
- ✅ Weight được tính toán chính xác (bất kể KG hay LBS)
- ✅ Có thể cấu hình từ admin mà không cần sửa code

