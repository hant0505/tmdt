# Phân Tích Vấn Đề Shipping GHN/GHTK Hiển Thị $0.00

## Tóm Tắt Vấn Đề
Module GHN và GHTK đang hiển thị chi phí vận chuyển là **$0.00** mặc dù đã có weight trong sản phẩm.

---

## 🔴 NGUYÊN NHÂN CHÍNH (Critical Issues)

### 1. **Vấn Đề Tỷ Giá Hối Đoái (Exchange Rate) - ⭐ CHÍNH LÀ NGUYÊN NHÂN CHÍNH**

**Vị trí lỗi:** `convertVndToUsd()` trong cả hai module

**Mã code có vấn đề:**
```php
private function convertVndToUsd(float $amountVND): float
{
    try {
        $store = $this->storeManager->getStore();
        $baseCurrencyCode = $store->getBaseCurrencyCode(); 

        if ($baseCurrencyCode === 'USD') {
            $currency = $this->currencyFactory->create()->load('VND');
            $rate = $currency->getAnyRate('USD');  // ⚠️ VẤN ĐỀ
            return round($amountVND * $rate, 4);   // Nếu $rate = NULL → Kết quả = 0
        }
        return round($amountVND / 26310, 4); 
    }
}
```

**Giải thích:**
- `$currency->getAnyRate('USD')` trả về `NULL` nếu tỷ giá VND→USD chưa được cấu hình trong Magento
- `$amountVND * NULL = NULL → round(NULL, 4) = 0`
- Kết quả: **$0.00**

**Dấu hiệu nhận biết:**
- Shipping cost là chính xác 0 (không phải 0.01 hay số nhỏ)
- Fallback rate (26310) không được sử dụng

---

### 2. **Vấn Đề Đơn Vị Trọng Lượng (Weight Unit Mismatch)**

**Vị trí lỗi:** Cả hai file Ghtk.php và Ghn.php

**Mô tả:**
- Magento cho phép cấu hình đơn vị trọng lượng toàn cục là **KG hoặc LBS**
- Code hiện tại **luôn giả định weight là KG** nhưng không kiểm tra
- Nếu store dùng **LBS**, weight sẽ sai: 1 LBS được gửi như 1 KG (sai ~2.2 lần)

**Giải thích:**
```
Nếu product có weight = 2 LBS:
- Magento trả: 2 (đơn vị LBS)
- Code gửi API: weight=2 (nhưng API nghĩ là 2 KG)
- API nhận: 2000g thay vì 907g → Tính giá sai
- Tuy nhiên, điều này không gây $0.00, chỉ gây giá sai
```

---

### 3. **Vấn Đề Dữ Liệu Địa Chỉ Gửi (From District ID)**

**Vị trí lỗi:** GHN - hardcoded district ID

```php
private function getToDistrictId()
{
    return 3440; // Cầu Giấy HN - HARD CODED
}
```

**Vấn đề:**
- Chỉnh sửa `from_district_id` trong admin nhưng `getToDistrictId()` vẫn hardcoded
- Có thể không có route từ quận hardcoded đến quận khách hàng

---

## 🔧 GIẢI PHÁP

### **FIX 1: Khắc phục Tỷ Giá Hối Đoái (QUAN TRỌNG)**

**Dùng fallback rate thay vì getAnyRate():**

**File:** `[magento/app/code/Shipping/GHTK/Model/Carrier/Ghtk.php](magento/app/code/Shipping/GHTK/Model/Carrier/Ghtk.php#L101-L117)`

Thay:
```php
private function convertVndToUsd(float $amountVND): float
{
    try {
        $store = $this->storeManager->getStore();
        $baseCurrencyCode = $store->getBaseCurrencyCode(); 

        if ($baseCurrencyCode === 'USD') {
            $currency = $this->currencyFactory->create()->load('VND');
            $rate = $currency->getAnyRate('USD'); 
            return round($amountVND * $rate, 4);
        }

        return round($amountVND / 26310, 4); 
    } catch (\Exception $e) {
        $this->_logger->error('GHTK Currency Conversion Error: ' . $e->getMessage());
        return round($amountVND / 26310, 4); 
    }
}
```

Bằng:
```php
private function convertVndToUsd(float $amountVND): float
{
    try {
        $store = $this->storeManager->getStore();
        $baseCurrencyCode = $store->getBaseCurrencyCode(); 

        if ($baseCurrencyCode === 'USD') {
            $currency = $this->currencyFactory->create()->load('VND');
            $rate = $currency->getAnyRate('USD'); 
            
            // ✅ FIX: Kiểm tra rate có hợp lệ không, nếu không dùng fallback
            if ($rate && $rate > 0) {
                return round($amountVND * $rate, 4);
            }
            $this->_logger->warning('GHTK: Tỷ giá VND/USD không có, dùng fallback 26310');
        }

        return round($amountVND / 26310, 4); 
    } catch (\Exception $e) {
        $this->_logger->error('GHTK Currency Conversion Error: ' . $e->getMessage());
        return round($amountVND / 26310, 4); 
    }
}
```

---

**File:** `[magento/app/code/Shipping/GHN/Model/Carrier/Ghn.php](magento/app/code/Shipping/GHN/Model/Carrier/Ghn.php#L86-L102)`

Thay:
```php
private function convertVndToUsd(float $amountVND): float
{
    try {
        $store = $this->storeManager->getStore();
        $baseCurrencyCode = $store->getBaseCurrencyCode(); 

        if ($baseCurrencyCode === 'USD') {
            $currency = $this->currencyFactory->create()->load('VND');
            $rate = $currency->getAnyRate('USD'); 
            return round($amountVND * $rate, 4);
        }

        return round($amountVND / 26310, 4); 
    } catch (\Exception $e) {
        $this->_logger->error('GHN Currency Conversion Error: ' . $e->getMessage());
        return round($amountVND / 26310, 4); 
    }
}
```

Bằng:
```php
private function convertVndToUsd(float $amountVND): float
{
    try {
        $store = $this->storeManager->getStore();
        $baseCurrencyCode = $store->getBaseCurrencyCode(); 

        if ($baseCurrencyCode === 'USD') {
            $currency = $this->currencyFactory->create()->load('VND');
            $rate = $currency->getAnyRate('USD'); 
            
            // ✅ FIX: Kiểm tra rate có hợp lệ không, nếu không dùng fallback
            if ($rate && $rate > 0) {
                return round($amountVND * $rate, 4);
            }
            $this->_logger->warning('GHN: Tỷ giá VND/USD không có, dùng fallback 26310');
        }

        return round($amountVND / 26310, 4); 
    } catch (\Exception $e) {
        $this->_logger->error('GHN Currency Conversion Error: ' . $e->getMessage());
        return round($amountVND / 26310, 4); 
    }
}
```

---

### **FIX 2: Khắc phục Đơn Vị Trọng Lượng (Weight Unit Conversion)**

**File:** `[magento/app/code/Shipping/GHTK/Model/Carrier/Ghtk.php](magento/app/code/Shipping/GHTK/Model/Carrier/Ghtk.php#L51-L56)`

Thay:
```php
public function collectRates(RateRequest $request)
{
    if (!$this->getConfigData('active')) {
        return false;
    }

    $result = $this->_rateResultFactory->create();

    $weightInKg = $request->getPackageWeight() ?: 1;
```

Bằng:
```php
public function collectRates(RateRequest $request)
{
    if (!$this->getConfigData('active')) {
        return false;
    }

    $result = $this->_rateResultFactory->create();

    // ✅ FIX: Convert weight unit (LBS → KG nếu cần)
    $weight = $request->getPackageWeight() ?: 1;
    $weightInKg = $this->_convertWeightToKg($weight);
```

Thêm method mới ở cuối class:
```php
/**
 * Convert weight to KG based on store configuration
 * Magento weight unit: Default is KG, but can be LBS
 */
private function _convertWeightToKg(float $weight): float
{
    // Get weight unit from store config
    $weightUnit = $this->getConfigData('weight_unit') 
        ?? $this->_scopeConfig->getValue('general/locale/weight_unit');
    
    if ($weightUnit === 'lbs') {
        // 1 LB = 0.453592 KG
        return round($weight * 0.453592, 4);
    }
    
    return $weight; // Already in KG
}
```

---

**File:** `[magento/app/code/Shipping/GHN/Model/Carrier/Ghn.php](magento/app/code/Shipping/GHN/Model/Carrier/Ghn.php#L51-L56)`

Tương tự, thay:
```php
$weightInKg = $request->getPackageWeight() ?: 1;
$weightInGram = (int)($weightInKg * 1000);
```

Bằng:
```php
// ✅ FIX: Convert weight unit (LBS → KG nếu cần)
$weight = $request->getPackageWeight() ?: 1;
$weightInKg = $this->_convertWeightToKg($weight);
$weightInGram = (int)($weightInKg * 1000);
```

Và thêm method tương tự.

---

### **FIX 3: Sử dụng from_district_id từ Config (GHN)**

**File:** `[magento/app/code/Shipping/GHN/Model/Carrier/Ghn.php](magento/app/code/Shipping/GHN/Model/Carrier/Ghn.php#L59-L63)`

Thay:
```php
$fromDistrictId = (int)$this->getConfigData('from_district_id') ?: 3440;
```

Thêm field này vào `[magento/app/code/Shipping/GHN/etc/adminhtml/system.xml](magento/app/code/Shipping/GHN/etc/adminhtml/system.xml)`:

```xml
<field id="from_district_id" translate="label" type="text" sortOrder="50" showInDefault="1" showInWebsite="1" showInStore="0">
    <label>From District ID (Quận/Huyện gửi hàng)</label>
    <comment>Lấy từ GHN để xác định khu vực gửi hàng. Mặc định: 3440 (Cầu Giấy, Hà Nội)</comment>
</field>
```

---

### **FIX 4: Fix getToDistrictId() (GHN) - Không hardcoded**

**File:** `[magento/app/code/Shipping/GHN/Model/Carrier/Ghn.php](magento/app/code/Shipping/GHN/Model/Carrier/Ghn.php#L126-L131)`

Thay:
```php
private function getToDistrictId()
{
    return 3440; // Cầu Giấy HN
}
```

Bằng:
```php
private function getToDistrictId()
{
    // Lấy to_district_id từ config admin hoặc mặc định
    return (int)$this->getConfigData('to_district_id') ?: 3440; // Mặc định Cầu Giấy
}
```

Và thêm field vào system.xml:
```xml
<field id="to_district_id" translate="label" type="text" sortOrder="51" showInDefault="1" showInWebsite="1" showInStore="0">
    <label>To District ID (Quận/Huyện nhận hàng - mặc định)</label>
    <comment>Mặc định cho khách: 3440 (Cầu Giấy, Hà Nội)</comment>
</field>
```

---

## 📋 KIỂM TRA TRONG ADMIN

1. **Kiểm tra tỷ giá:**
   - Stores → Settings → Configuration → General → Currency Setup
   - Đảm bảo tỷ giá VND ↔ USD đã được import

2. **Kiểm tra API:**
   - Stores → Configuration → Sales → Shipping Methods → GHN/GHTK
   - Xác nhận API Token và Shop ID (GHN) đúng
   - Enable Sandbox Mode để test

3. **Kiểm tra weight:**
   - Stores → Settings → Configuration → General → Locale Options
   - Kiểm tra "Weight Unit" đang dùng (KG hoặc LBS)

---

## 📍 DEBUG LOG

Kiểm tra file log:
```
var/log/system.log
```

Tìm các dòng:
- `Currency Conversion Error`
- `API Error`
- `API Exception`

---

## ✅ KỲ VỌNG SAU KHI FIX

Sau khi áp dụng các fix trên:
- ✅ Shipping cost sẽ hiển thị giá thực (VND convert sang USD đúng)
- ✅ Weight được tính toán đúng cho API (LBS → KG nếu cần)
- ✅ District ID có thể cấu hình trong admin thay vì hardcoded
- ✅ Có fallback rate an toàn nếu exchange rate không có

