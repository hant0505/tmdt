<?php

namespace Vendor\Weather\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\DeploymentConfig;

class Data extends AbstractHelper
{
    protected $deploymentConfig;

    public function __construct(
        DeploymentConfig $deploymentConfig
    ) {
        $this->deploymentConfig = $deploymentConfig;
    }

    public function getApiKey()
    {
        return $this->deploymentConfig->get('weather/api_key');
    }

    public function getDefaultCity()
    {
        return $this->deploymentConfig->get('weather/default_city');
    }
}