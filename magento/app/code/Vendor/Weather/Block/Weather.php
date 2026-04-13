<?php

namespace Vendor\Weather\Block;

use Magento\Framework\View\Element\Template;
use Vendor\Weather\Helper\Data;

class Weather extends Template
{
    protected $helper;

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
        $city = $this->getRequest()->getParam('city');
        if ($city) {
            return $city;
        }

        return $this->helper->getDefaultCity() ?? 'Hanoi';
    }

    public function getWeatherData()
    {
        $city = $this->getCity();
        $apiKey = $this->helper->getApiKey();

        $url = "https://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$apiKey}&units=metric";

        try {
            $response = file_get_contents($url);

            if ($response === false) {
                return null;
            }

            return json_decode($response, true);
        } catch (\Exception $e) { // ✅ thêm dấu \
            return null;
        }
    }

    public function getTemperature()
    {
        $data = $this->getWeatherData();
        return $data['main']['temp'] ?? 'N/A';
    }

    public function getDescription()
    {
        $data = $this->getWeatherData();
        return $data['weather'][0]['description'] ?? '';
    }

    public function getHumidity()
    {
        $data = $this->getWeatherData();
        return $data['main']['humidity'] ?? '';
    }
}