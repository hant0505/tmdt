<?php
namespace Shipping\GHN\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Psr\Log\LoggerInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Directory\Model\CurrencyFactory;           // Thêm
use Magento\Store\Model\StoreManagerInterface;        // Thêm

class Ghn extends AbstractCarrier implements CarrierInterface
{
    protected $_code = 'ghn';
    protected $_isFixed = false;

    protected $_rateResultFactory;
    protected $_rateMethodFactory;
    protected $_curl;
    protected $_jsonSerializer;
    protected $currencyFactory;
    protected $storeManager;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        Curl $curl,
        Json $jsonSerializer,
        CurrencyFactory $currencyFactory,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_curl = $curl;
        $this->_jsonSerializer = $jsonSerializer;
        $this->currencyFactory = $currencyFactory;
        $this->storeManager = $storeManager;

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigData('active')) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();

        // Convert weight to KG based on store configuration
        $weight = $request->getPackageWeight() ?: 1;
        $weightInKg = $this->_convertWeightToKg($weight);
        $weightInGram = (int)($weightInKg * 1000);

        // Lấy from_district_id từ config (không fix cứng)
        $fromDistrictId = (int)$this->getConfigData('from_district_id') ?: 3440; // Mặc định Cầu Giấy - Hà Nội

        $expressFeeVND = $this->_getGhnFeeFromApi($weightInGram, $fromDistrictId, 2);

        if ($expressFeeVND === false) {
            $this->_logger->error('Shipping_GHN: Dùng giá Fallback vì API lỗi');
            $expressFeeVND = 35000;
        }

        // === CONVERT VND → USD (Base Currency) ===
        $expressFeeUSD = $this->convertVndToUsd($expressFeeVND);

        // Method 1: GHN Express
        $methodExpress = $this->_createRateMethod('express', 'GHN Express (Giao nhanh)');
        $methodExpress->setPrice($expressFeeUSD);
        $methodExpress->setCost($expressFeeUSD);
        $result->append($methodExpress);

        // Method 2: GHN Standard
        $standardFeeVND = (int)($expressFeeVND * 0.8);
        $standardFeeUSD = $this->convertVndToUsd($standardFeeVND);

        $methodStandard = $this->_createRateMethod('standard', 'GHN Standard (Tiết kiệm)');
        $methodStandard->setPrice($standardFeeUSD);
        $methodStandard->setCost($standardFeeUSD);
        $result->append($methodStandard);

        return $result;
    }

    /**
     * Convert VND sang USD theo tỷ giá Base Currency
     */
    private function convertVndToUsd(float $amountVND): float
    {
        try {
            $store = $this->storeManager->getStore();
            $baseCurrencyCode = $store->getBaseCurrencyCode(); // Usually USD

            if ($baseCurrencyCode === 'USD') {
                $currency = $this->currencyFactory->create()->load('VND');
                $rate = $currency->getAnyRate('USD'); // Exchange rate VND → USD

                // Check if rate is valid, otherwise use fallback
                if ($rate && $rate > 0) {
                    return round($amountVND * $rate, 4);
                }
                $this->_logger->warning('GHN: Exchange rate VND/USD not found, using fallback 26310');
            }

            // Default for non-USD base currency
            return round($amountVND / 26310, 4);
        } catch (\Exception $e) {
            $this->_logger->error('GHN Currency Conversion Error: ' . $e->getMessage());
            return round($amountVND / 26310, 4); // Safe fallback
        }
    }

    private function _getGhnFeeFromApi($weight, $fromDistrictId, $serviceTypeId)
    {
        $token = trim((string)$this->getConfigData('api_token'));
        $shopId = trim((string)$this->getConfigData('shop_id'));

        if (empty($token) || empty($shopId)) {
            $this->_logger->error('GHN: Thiếu Token hoặc ShopID');
            return false;
        }

        $apiUrl = 'https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee';

        $params = [
            "service_type_id" => (int)$serviceTypeId,
            "from_district_id" => (int)$fromDistrictId,
            "to_district_id"   => $this->getToDistrictId(), // giữ logic cũ của bạn
            "weight"           => (int)$weight,
            "length"           => 10,
            "width"            => 10,
            "height"           => 10
        ];

        $this->_curl->setHeaders([
            'Token' => $token,
            'ShopId' => $shopId,
            'Content-Type' => 'application/json'
        ]);

        try {
            $this->_curl->post($apiUrl, $this->_jsonSerializer->serialize($params));
            $response = $this->_curl->getBody();
            $resultData = $this->_jsonSerializer->unserialize($response);

            if (isset($resultData['code']) && $resultData['code'] == 200 && isset($resultData['data']['total'])) {
                return (float)$resultData['data']['total'];
            }

            $this->_logger->error('GHN API Error: ' . $response);
        } catch (\Exception $e) {
            $this->_logger->critical('GHN API Exception: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Lấy to_district_id từ config (không hardcode)
     */
    private function getToDistrictId()
    {
        $districtId = (int)$this->getConfigData('to_district_id');
        if (!$districtId) {
            $districtId = 3440; // Mặc định Cầu Giấy HN
        }
        return $districtId;
    }

    private function _createRateMethod($methodCode, $methodTitle)
    {
        $method = $this->_rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod($methodCode);
        $method->setMethodTitle($methodTitle);
        return $method;
    }

    public function getAllowedMethods()
    {
        return [
            'express'  => 'GHN Express',
            'standard' => 'GHN Standard'
        ];
    }

    /**
     * Convert weight to KG based on store weight unit configuration
     * Magento supports both KG and LBS as weight units
     */
    private function _convertWeightToKg(float $weight): float
    {
        try {
            // Try to get weight unit from system configuration
            $weightUnit = $this->_scopeConfig->getValue(
                'general/locale/weight_unit',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
            
            // If weight unit is LBS, convert to KG
            if ($weightUnit === 'lbs') {
                // 1 LB = 0.453592 KG
                $convertedWeight = round($weight * 0.453592, 4);
                $this->_logger->info('GHN Weight conversion: ' . $weight . ' lbs → ' . $convertedWeight . ' kg');
                return $convertedWeight;
            }
        } catch (\Exception $e) {
            $this->_logger->warning('GHN: Could not determine weight unit, assuming KG');
        }
        
        // Default: assume already in KG
        return $weight;
    }
}