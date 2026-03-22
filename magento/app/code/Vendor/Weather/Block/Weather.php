<?php

namespace Vendor\Weather\Block;

use Magento\Framework\View\Element\Template;

class Weather extends Template
{
    public function getWelcomeMessage()
    {
        return "Hello from Block Yachiyooooo~~";
    }

    public function getTemperature()
    {
        // fake data (sau này thay bằng API OpenWeather)
        return rand(25, 35);
    }
}