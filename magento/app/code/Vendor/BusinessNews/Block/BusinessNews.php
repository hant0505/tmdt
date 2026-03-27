<?php
namespace Vendor\BusinessNews\Block;

use Magento\Framework\View\Element\Template;
use Vendor\BusinessNews\Helper\Data;

class BusinessNews extends Template
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

    public function getNewsList(int $limit = 8)
    {
        return $this->helper->getBusinessNews($limit);
    }
}