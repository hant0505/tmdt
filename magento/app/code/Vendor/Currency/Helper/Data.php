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

    protected $cache;
    protected $serializer;

    public function __construct(
        Context $context,
        CacheInterface $cache,
        SerializerInterface $serializer
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
        parent::__construct($context);
    }

    public function getExchangeRates()
    {
        $cachedData = $this->cache->load(self::CACHE_KEY);
        if ($cachedData) {
            return $this->serializer->unserialize($cachedData);
        }

        $url = 'https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=68';
        $xmlContent = file_get_contents($url);

        if (!$xmlContent) {
            return ['error' => 'Không lấy được dữ liệu từ Vietcombank'];
        }

        $xml = simplexml_load_string($xmlContent);
        if (!$xml) {
            return ['error' => 'Dữ liệu XML không hợp lệ'];
        }

        $rates = [
            'datetime' => (string)$xml->DateTime,
            'source'   => (string)$xml->Source,
            'rates'    => []
        ];

        foreach ($xml->Exrate as $exrate) {
            $rates['rates'][] = [
                'code'      => (string)$exrate['CurrencyCode'],
                'name'      => trim((string)$exrate['CurrencyName']),
                'buy'       => (string)$exrate['Buy'],
                'transfer'  => (string)$exrate['Transfer'],
                'sell'      => (string)$exrate['Sell']
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