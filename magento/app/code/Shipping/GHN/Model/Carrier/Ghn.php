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

class Ghn extends AbstractCarrier implements CarrierInterface
{
    protected $_code = 'ghn';
    protected $_isFixed = false;
    protected $_rateResultFactory;
    protected $_rateMethodFactory;
    protected $_curl;
    protected $_jsonSerializer;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        Curl $curl,
        Json $jsonSerializer,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_curl = $curl;
        $this->_jsonSerializer = $jsonSerializer;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigData('active')) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();

        // 1. Tính toán khối lượng ra Gram
        $weightInKg = $request->getPackageWeight() ?: 1; 
        $weightInGram = (int)($weightInKg * 1000); 

        // 2. Map District ID (Mặc định Cầu Giấy - HN: 3440, nếu có HCM chuyển thành Q3: 1454)
        $destCity = strtolower((string)$request->getDestCity() ?: $request->getDestRegionCode());
        $toDistrictId = 3440; 
        if (strpos($destCity, 'hcm') !== false || strpos($destCity, 'ho chi minh') !== false) {
            $toDistrictId = 1454;
        }

        // 3. Gọi API tính phí
        $expressFee = $this->_getGhnFeeFromApi($weightInGram, $toDistrictId, 2);

        // Fallback cứu sinh nếu API chết
        if ($expressFee === false) {
            $this->_logger->error('Shipping_GHN: Dùng giá Fallback vì API lỗi');
            $expressFee = 35000; 
        }

        // Method 1: GHN Express
        $methodExpress = $this->_createRateMethod('express', 'GHN Express (Giao nhanh)');
        $methodExpress->setPrice($expressFee); 
        $methodExpress->setCost($expressFee);
        $result->append($methodExpress);

        // Method 2: GHN Standard (-20% từ giá Express)
        $standardFee = (int)($expressFee * 0.8);
        $methodStandard = $this->_createRateMethod('standard', 'GHN Standard (Tiết kiệm)');
        $methodStandard->setPrice($standardFee);
        $methodStandard->setCost($standardFee);
        $result->append($methodStandard);

        return $result;
    }

    private function _getGhnFeeFromApi($weight, $toDistrictId, $serviceTypeId)
    {
        $token = trim((string)$this->getConfigData('api_token'));
        $shopId = trim((string)$this->getConfigData('shop_id'));

        if (empty($token) || empty($shopId)) {
            $this->_logger->error('GHN: Thiếu Token hoặc ShopID trong cấu hình');
            return false;
        }

        $apiUrl = 'https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee';
        
        $params = [
            "service_type_id" => (int)$serviceTypeId,
            "from_district_id" => 1442, // Fix cứng Q1 HCM
            "to_district_id" => (int)$toDistrictId,
            "weight" => (int)$weight,
            "length" => 10,
            "width" => 10,
            "height" => 10
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

            if (isset($resultData['code']) && $resultData['code'] == 200) {
                return $resultData['data']['total'];
            }
            
            $this->_logger->error('GHN API Error Response: ' . $response);
        } catch (\Exception $e) {
            $this->_logger->critical('GHN API Exception: ' . $e->getMessage());
        }

        return false;
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
            'express' => 'GHN Express',
            'standard' => 'GHN Standard'
        ];
    }
}