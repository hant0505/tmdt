<?php

namespace Vendor\Weather\Block;

use Magento\Framework\View\Element\Template;
use Vendor\Weather\Helper\Data;

class Weather extends Template
{
    protected $helper;
    private $weatherData;

    public function __construct(
        Template\Context $context,
        Data $helper,
        array $data = []
    ) {
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    public function getCity()
    {
        $city = trim((string) $this->getRequest()->getParam('city'));
        if ($city) {
            return $city;
        }

        return $this->helper->getDefaultCity();
    }

    public function getWeatherData()
    {
        if ($this->weatherData === null) {
            $this->weatherData = $this->helper->getWeatherData($this->getCity());
        }

        return $this->weatherData;
    }

    public function getDisplayCity()
    {
        $data = $this->getWeatherData();
        return $data['name'] ?? $this->helper->getDisplayCity($this->getCity());
    }

    public function getCountryCode()
    {
        $data = $this->getWeatherData();
        return $data['sys']['country'] ?? '';
    }

    public function getTemperature()
    {
        $data = $this->getWeatherData();
        return isset($data['main']['temp']) ? (int) round((float) $data['main']['temp']) : null;
    }

    public function getFeelsLike()
    {
        $data = $this->getWeatherData();
        return isset($data['main']['feels_like']) ? (int) round((float) $data['main']['feels_like']) : null;
    }

    public function getDescription()
    {
        $data = $this->getWeatherData();
        return $data['weather'][0]['description'] ?? '';
    }

    public function getHumidity()
    {
        $data = $this->getWeatherData();
        return $data['main']['humidity'] ?? null;
    }

    public function getWindSpeed()
    {
        $data = $this->getWeatherData();
        return isset($data['wind']['speed']) ? round((float) $data['wind']['speed'], 1) : null;
    }

    public function getIconUrl()
    {
        $data = $this->getWeatherData();
        $icon = $data['weather'][0]['icon'] ?? '';
        if (!$icon) {
            return '';
        }

        return 'https://openweathermap.org/img/wn/' . $icon . '@2x.png';
    }

    public function getError()
    {
        $data = $this->getWeatherData();
        return $data['error'] ?? '';
    }

    public function getPopularCities()
    {
        return [
            'Hanoi,VN' => __('Hà Nội'),
            'Ho Chi Minh City,VN' => __('TP. HCM'),
            'Da Nang,VN' => __('Đà Nẵng'),
            'Singapore,SG' => __('Singapore'),
            'Tokyo,JP' => __('Tokyo')
        ];
    }

    public function getSearchActionUrl()
    {
        if ($this->getData('variant') === 'home') {
            return $this->getUrl('');
        }

        return $this->getUrl('weather');
    }

    public function getWidgetVariant()
    {
        return $this->getData('variant') === 'home' ? 'home' : 'page';
    }
}
