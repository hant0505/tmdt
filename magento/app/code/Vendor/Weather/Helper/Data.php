<?php

namespace Vendor\Weather\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Serialize\SerializerInterface;

class Data extends AbstractHelper
{
    private const WEATHER_ENDPOINT = 'https://api.openweathermap.org/data/2.5/weather';
    private const CACHE_LIFETIME = 600;

    protected $deploymentConfig;
    private $cache;
    private $serializer;

    public function __construct(
        Context $context,
        DeploymentConfig $deploymentConfig,
        CacheInterface $cache,
        SerializerInterface $serializer
    ) {
        parent::__construct($context);
        $this->deploymentConfig = $deploymentConfig;
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    public function getApiKey()
    {
        return $this->deploymentConfig->get('weather/api_key')
            ?: $this->deploymentConfig->get('weather/openweather_api_key')
            ?: $this->deploymentConfig->get('openweather/api_key');
    }

    public function getDefaultCity()
    {
        return $this->deploymentConfig->get('weather/default_city') ?: 'Hanoi,VN';
    }

    public function getWeatherData($city)
    {
        $city = trim((string) $city);
        if ($city === '') {
            $city = $this->getDefaultCity();
        }

        $apiKey = trim((string) $this->getApiKey());
        if ($apiKey === '') {
            return [
                'error' => __('Chưa cấu hình OpenWeather API key trong env.php.'),
                'name' => $this->getDisplayCity($city)
            ];
        }

        $cacheKey = 'tekntek_weather_' . md5(strtolower($city));
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return $this->serializer->unserialize($cached);
        }

        $url = self::WEATHER_ENDPOINT . '?' . http_build_query([
            'q' => $city,
            'appid' => $apiKey,
            'units' => 'metric',
            'lang' => 'vi'
        ]);

        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'ignore_errors' => true
            ]
        ]);

        try {
            $response = @file_get_contents($url, false, $context);
            if (!$response) {
                return [
                    'error' => __('Không lấy được dữ liệu thời tiết từ OpenWeather.'),
                    'name' => $this->getDisplayCity($city)
                ];
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                return [
                    'error' => __('Dữ liệu thời tiết không hợp lệ.'),
                    'name' => $this->getDisplayCity($city)
                ];
            }

            $code = (string) ($data['cod'] ?? '');
            if ($code !== '200') {
                return [
                    'error' => $data['message'] ?? __('Không tìm thấy dữ liệu thời tiết cho vị trí này.'),
                    'name' => $this->getDisplayCity($city)
                ];
            }

            $this->cache->save(
                $this->serializer->serialize($data),
                $cacheKey,
                ['tekntek_weather'],
                self::CACHE_LIFETIME
            );

            return $data;
        } catch (\Throwable $e) {
            return [
                'error' => __('Không lấy được dữ liệu thời tiết từ OpenWeather.'),
                'name' => $this->getDisplayCity($city)
            ];
        }
    }

    public function getDisplayCity($city)
    {
        $city = trim((string) $city);
        if ($city === '') {
            return 'Hanoi';
        }

        return trim(explode(',', $city)[0]);
    }
}
