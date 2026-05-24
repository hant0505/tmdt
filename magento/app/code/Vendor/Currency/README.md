# TeknTek Currency Exchange Widget

Magento 2.4.8-p3 module for displaying Vietcombank exchange rates inside `TeknTek/OrangeTheme`.

## Features

- Fetches exchange rates from the public Vietcombank XML feed.
- Uses Magento cache for the full rates payload.
- Maps currency codes to country codes for flags.
- Renders sharp SVG flags from FlagCDN with emoji fallback.
- Provides OrangeTheme layout and CSS for the `/currency` page.
- Removes Compare Products and My Wish List sidebars on the currency page.

## API keys

No API key is required.

| Purpose | Service | API key |
| --- | --- | --- |
| Exchange rates | Vietcombank XML feed | Not required |
| Flags | FlagCDN SVG URLs | Not required |

If a paid provider is added later, put the key in `app/etc/env.php` and read it through Magento deployment config.

## Main files

```text
app/code/Vendor/Currency/
├── Block/Rates.php
├── Controller/Index/Index.php
├── Helper/Data.php
├── etc/frontend/routes.xml
└── view/frontend/layout/currency_index_index.xml

app/design/frontend/TeknTek/OrangeTheme/Vendor_Currency/
├── layout/currency_index_index.xml
├── layout/vendor_currency_index_index.xml
├── templates/currency-widget.phtml
└── web/css/currency-widget.css
```

## URL

```text
http://localhost/currency
```

## Cache

The exchange-rate payload is cached for 1 hour with the `vietcombank_currency` tag.

Clear cache after edits:

```bash
bin/magento cache:clean
```

## Adding currencies

Add or adjust mappings in `Vendor\Currency\Helper\Data::$currencyToCountryMap`.

Each rate row exposes:

```php
[
    'code' => 'USD',
    'name' => 'US DOLLAR',
    'country_name' => 'United States',
    'country_code' => 'US',
    'flag' => '🇺🇸',
    'flag_url' => 'https://flagcdn.com/us.svg',
    'buy' => '...',
    'transfer' => '...',
    'sell' => '...'
]
```
