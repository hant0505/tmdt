# Currency Widget API Configuration

## API keys

No API key is required for the current implementation.

The widget uses:

| Purpose | Service | URL pattern | API key |
| --- | --- | --- | --- |
| Exchange rates | Vietcombank XML feed | `https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=68` | Not required |
| Country flags | FlagCDN | `https://flagcdn.com/{country_code}.svg` | Not required |

You do not need to add any API key to `app/etc/env.php` for this feature.

## Data flow

1. User opens `/currency`.
2. `Vendor\Currency\Helper\Data::getExchangeRates()` checks Magento cache.
3. If cache is empty, the helper fetches the Vietcombank XML feed.
4. Each currency code is mapped to an ISO 3166-1 alpha-2 country code.
5. The frontend renders the SVG flag from FlagCDN and falls back to an emoji flag if the image cannot load.
6. Exchange-rate data is cached for 1 hour with the `vietcombank_currency` cache tag.

## Vietcombank feed

Endpoint:

```text
https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=68
```

Response format: XML.

Authentication: none.

The module currently reads:

- `DateTime`
- `Source`
- `Exrate` rows with `CurrencyCode`, `CurrencyName`, `Buy`, `Transfer`, and `Sell`

## FlagCDN

Flag URLs are generated from the currency-country mapping in `Helper/Data.php`.

Examples:

```text
https://flagcdn.com/us.svg
https://flagcdn.com/eu.svg
https://flagcdn.com/jp.svg
```

Authentication: none.

The current UI uses SVG images because they remain sharp in the exchange-rate table and do not require another backend HTTP request per currency.

## Optional env.php configuration

No values are required right now. If you later want these endpoints configurable from `env.php`, add keys like:

```php
'currency_widget' => [
    'vietcombank_url' => 'https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=68',
    'flagcdn_base_url' => 'https://flagcdn.com',
    'cache_lifetime' => 3600
]
```

Then inject `Magento\Framework\App\DeploymentConfig` into `Vendor\Currency\Helper\Data` and read those values before falling back to the defaults.

## If you later choose a paid flag API

The current code does not need this. If you switch to a paid provider later:

1. Create an account on the provider site.
2. Generate an API key from its dashboard.
3. Store the key in `app/etc/env.php`, not in Git.
4. Read it through `DeploymentConfig` in the helper.
5. Keep an image or emoji fallback so the rates page still works if that API is unavailable.

## Local checks

```bash
bin/magento cache:clean
bin/magento setup:static-content:deploy -f
```

Open:

```text
http://localhost/currency
```

The page should render as a one-column OrangeTheme page without Compare Products or My Wish List sidebars.
