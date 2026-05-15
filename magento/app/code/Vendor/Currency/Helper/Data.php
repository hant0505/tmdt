<?php
namespace Vendor\Currency\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

class Data extends AbstractHelper
{
    const CACHE_KEY = 'vietcombank_currency_rates';
    const CACHE_LIFETIME = 3600; // 1 giờ (cập nhật mỗi giờ)
    const FLAG_CDN_BASE_URL = 'https://flagcdn.com';

    protected $cache;
    protected $serializer;
    
    // Mapping currency code to country code for flags
    protected $currencyToCountryMap = [
        'AUD' => 'AU',
        'CAD' => 'CA',
        'CHF' => 'CH',
        'CNY' => 'CN',
        'DKK' => 'DK',
        'EUR' => 'EU',
        'GBP' => 'GB',
        'HKD' => 'HK',
        'INR' => 'IN',
        'JPY' => 'JP',
        'KRW' => 'KR',
        'KWD' => 'KW',
        'MYR' => 'MY',
        'NOK' => 'NO',
        'RUB' => 'RU',
        'SAR' => 'SA',
        'SEK' => 'SE',
        'SGD' => 'SG',
        'THB' => 'TH',
        'TWD' => 'TW',
        'USD' => 'US',
        'AED' => 'AE',
        'BRL' => 'BR',
        'IDR' => 'ID',
        'PHP' => 'PH',
    ];

    public function __construct(
        Context $context,
        CacheInterface $cache,
        SerializerInterface $serializer
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
        parent::__construct($context);
    }

    /**
     * Get country flag emoji for currency code.
     */
    public function getCountryFlagForCurrency($currencyCode)
    {
        $countryCode = $this->currencyToCountryMap[$currencyCode] ?? null;
        if (!$countryCode) {
            return '🌍';
        }

        return $this->generateCountryFlagEmoji($countryCode);
    }

    /**
     * Get SVG flag URL from FlagCDN.
     */
    public function getCountryFlagUrlForCurrency($currencyCode)
    {
        $countryCode = $this->currencyToCountryMap[$currencyCode] ?? null;
        if (!$countryCode) {
            return '';
        }

        return self::FLAG_CDN_BASE_URL . '/' . strtolower($countryCode) . '.svg';
    }

    /**
     * Generate flag emoji from ISO-2 country code
     */
    protected function generateCountryFlagEmoji($countryCode)
    {
        $countryCode = strtoupper($countryCode);
        $flag = '';
        for ($i = 0; $i < strlen($countryCode); $i++) {
            $flag .= html_entity_decode('&#' . (0x1F1E6 + ord($countryCode[$i]) - ord('A')) . ';');
        }
        return $flag;
    }

    /**
     * Get country name for currency code
     */
    public function getCountryNameForCurrency($currencyCode)
    {
        $countryMap = [
            'AUD' => 'Australia',
            'CAD' => 'Canada',
            'CHF' => 'Switzerland',
            'CNY' => 'China',
            'DKK' => 'Denmark',
            'EUR' => 'Eurozone',
            'GBP' => 'United Kingdom',
            'HKD' => 'Hong Kong',
            'INR' => 'India',
            'JPY' => 'Japan',
            'KRW' => 'South Korea',
            'KWD' => 'Kuwait',
            'MYR' => 'Malaysia',
            'NOK' => 'Norway',
            'RUB' => 'Russia',
            'SAR' => 'Saudi Arabia',
            'SEK' => 'Sweden',
            'SGD' => 'Singapore',
            'THB' => 'Thailand',
            'TWD' => 'Taiwan',
            'USD' => 'United States',
            'AED' => 'UAE',
            'BRL' => 'Brazil',
            'IDR' => 'Indonesia',
            'PHP' => 'Philippines',
        ];

        return $countryMap[$currencyCode] ?? '';
    }

    public function getExchangeRates()
    {
        $cachedData = $this->cache->load(self::CACHE_KEY);
        if ($cachedData) {
            return $this->serializer->unserialize($cachedData);
        }

        $url = 'https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=68';
        
        // Use stream context with timeout
        $context = stream_context_create([
            'http' => ['timeout' => 10],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        
        $xmlContent = @file_get_contents($url, false, $context);

        if (!$xmlContent) {
            return ['error' => 'Không lấy được dữ liệu từ Vietcombank'];
        }

        $xml = @simplexml_load_string($xmlContent);
        if (!$xml) {
            return ['error' => 'Dữ liệu XML không hợp lệ'];
        }

        $rates = [
            'datetime' => (string)$xml->DateTime,
            'source'   => (string)$xml->Source,
            'rates'    => []
        ];

        foreach ($xml->Exrate as $exrate) {
            $code = (string)$exrate['CurrencyCode'];
            $rates['rates'][] = [
                'code'           => $code,
                'name'           => trim((string)$exrate['CurrencyName']),
                'country_name'   => $this->getCountryNameForCurrency($code),
                'country_code'   => $this->currencyToCountryMap[$code] ?? '',
                'flag'           => $this->getCountryFlagForCurrency($code),
                'flag_url'       => $this->getCountryFlagUrlForCurrency($code),
                'buy'            => (string)$exrate['Buy'],
                'transfer'       => (string)$exrate['Transfer'],
                'sell'           => (string)$exrate['Sell']
            ];
        }

        // Lưu vào cache
        $this->cache->save(
            $this->serializer->serialize($rates),
            self::CACHE_KEY,
            ['vietcombank_currency'],
            self::CACHE_LIFETIME
        );

        return $rates;
    }
}
