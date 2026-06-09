# ZaloPay Sandbox

Sandbox/test only. Khong dung tien that, production endpoint, hoac production key.

Admin config: Stores > Configuration > Sales > Payment Methods > ZaloPay Sandbox.

Public test key:
- AppID: 2554
- Key1 / Mac Key: sdngKKJmqEMzvh5QQcdD2A9XBSKUNaYn
- Key2 / Callback Key: trMrHtvjo6myautxDUiAcYsVtaeQ8nhf
- Create Order Endpoint: https://sb-openapi.zalopay.vn/v2/create

Callback URL khi deploy host:
https://your-domain.com/zalopay/payment/callback

Redirect URL:
https://your-domain.com/zalopay/payment/returnurl

Lenh cai module:

```bash
php bin/magento module:enable Payment_ZaloPay
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

Kiem tra order: Admin > Sales > Orders. Order moi o pending_payment, callback hop le se chuyen processing va co comment "Paid by ZaloPay Sandbox".

Loi thuong gap:
- Callback can domain HTTPS public de ZaloPay goi ve.
- Sai Key2 thi callback bi tu choi.
- Khong co order_url thi kiem tra endpoint sandbox va amount VND.
