<?php
namespace Vendor\Currency\Block;

use Magento\Framework\View\Element\Template;
use Vendor\Currency\Helper\Data as CurrencyHelper;

class Rates extends Template
{
    protected $currencyHelper;

    public function __construct(
        Template\Context $context,
        CurrencyHelper $currencyHelper,
        array $data = []
    ) {
        $this->currencyHelper = $currencyHelper;
        parent::__construct($context, $data);
    }

    protected function _construct()
    {
        parent::_construct();
        $this->addData([
            'cache_lifetime' => 3600,
            'cache_tags'     => ['vietcombank_currency']
        ]);
    }

    public function getRatesData()
    {
        return $this->currencyHelper->getExchangeRates();
    }

    /**
     * Format giá trị tiền tệ an toàn (xử lý dấu phẩy và chuỗi '-')
     */
    public function formatCurrencyValue($value)
    {
        if ($value === '-' || $value === '' || $value === null) {
            return '-';
        }

        // Loại bỏ dấu phẩy và khoảng trắng
        $cleanValue = str_replace([',', ' '], '', trim($value));

        // Nếu sau khi làm sạch vẫn không phải số → trả về nguyên bản
        if (!is_numeric($cleanValue)) {
            return htmlspecialchars($value);
        }

        return number_format((float)$cleanValue, 2);
    }
}