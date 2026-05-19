<?php
namespace Shipping\GHTK\Model\Carrier;

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
use Magento\Directory\Model\CurrencyFactory;      // Thêm
use Magento\Store\Model\StoreManagerInterface;    // Thêm

class Ghtk extends AbstractCarrier implements CarrierInterface
{
    protected $_code = 'ghtk';
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

        $weightInKg = $request->getPackageWeight() ?: 1;

        // Mặc định khách ở Cầu Giấy, Hà Nội nếu chưa nhập
        $destProvince = $request->getDestCity() ?: 'Hà Nội';
        $destDistrict = $request->getDestArea() ?: 'Quận Cầu Giấy';

        // Lấy giá VND từ API
        $ghtkFeeVND = $this->_getGhtkFeeFromApi($weightInKg, $destProvince, $destDistrict);

        if ($ghtkFeeVND === false) {
            $this->_logger->error('Shipping_GHTK: Bị lỗi API, đang kích hoạt phí Fallback 30k.');
            $ghtkFeeVND = 30000;
        }

        // Convert VND -> USD
        $ghtkFeeUSD = $this->convertVndToUsd($ghtkFeeVND);

        // Tạo Method Standard
        $methodStandard = $this->_createRateMethod('standard', 'GHTK Tiết kiệm');
        $methodStandard->setPrice($ghtkFeeUSD);
        $methodStandard->setCost($ghtkFeeUSD);
        $result->append($methodStandard);

        // Tạo Method Express
        $expressFeeVND = (int)($ghtkFeeVND * 1.5);
        $expressFeeUSD = $this->convertVndToUsd($expressFeeVND);
        $methodExpress = $this->_createRateMethod('express', 'GHTK Hỏa tốc');
        $methodExpress->setPrice($expressFeeUSD);
        $methodExpress->setCost($expressFeeUSD);
        $result->append($methodExpress);

        return $result;
    }

    /**
     * Convert VND sang USD theo tỷ giá Base Currency
     */
    private function convertVndToUsd(float $amountVND): float
    {
        try {
            $store = $this->storeManager->getStore();
            $baseCurrencyCode = $store->getBaseCurrencyCode(); 

            if ($baseCurrencyCode === 'USD') {
                $currency = $this->currencyFactory->create()->load('VND');
                $rate = $currency->getAnyRate('USD'); 
                return round($amountVND * $rate, 4);
            }

            return round($amountVND / 26310, 4); 
        } catch (\Exception $e) {
            $this->_logger->error('GHTK Currency Conversion Error: ' . $e->getMessage());
            return round($amountVND / 26310, 4); 
        }
    }

    private function _getGhtkFeeFromApi($weight, $province, $district)
    {
        $token = trim((string)$this->getConfigData('api_token'));
        if (empty($token)) {
            $this->_logger->error('GHTK: Thiếu API Token trong cấu hình Admin.');
            return false;
        }

        $isSandbox = $this->getConfigData('sandbox_mode');
        $baseUrl = $isSandbox ? 'https://services.ghtk.vn' : 'https://services.giaohangtietkiem.vn';
        
        $queryData = [
            'pick_province' => 'Hà Nội', // Gửi từ Hà Nội
            'pick_district' => 'Quận Cầu Giấy', // Gửi từ Cầu Giấy
            'province'      => $province,  
            'district'      => $district,
            'weight'        => (float)$weight,
            'deliver_option'=> 'none' 
        ];

        $apiUrl = $baseUrl . '/services/shipment/fee?' . http_build_query($queryData);

        $this->_curl->setHeaders([
            'Token' => $token,
            'X-Client-Source' => 'Magento2'
        ]);

        try {
            $this->_curl->get($apiUrl);
            $response = $this->_curl->getBody();
            $resultData = $this->_jsonSerializer->unserialize($response);

            if (isset($resultData['success']) && $resultData['success'] == true) {
                return $resultData['fee']['fee']; 
            }

            $this->_logger->error('GHTK API Error Response: ' . $response);
        } catch (\Exception $e) {
            $this->_logger->critical('GHTK API Exception: ' . $e->getMessage());
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
            'standard' => 'GHTK Tiết kiệm',
            'express' => 'GHTK Hỏa tốc'
        ];
    }
}