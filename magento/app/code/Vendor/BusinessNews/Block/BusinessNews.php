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

    /**
     * Tất cả bài (dùng cho main grid + phân trang JS)
     * Lấy nhiều để JS chia page
     */
    public function getNewsList(int $limit = 20): array
    {
        return $this->helper->getBusinessNews($limit);
    }

    /**
     * Bài nổi bật (sidebar) — lấy từ RSS thi-truong, KHÁC với main list
     */
    public function getFeaturedNews(): array
    {
        return $this->helper->getMarketNews(6);
    }

    /**
     * Gadgets section — bài từ offset 8 trở đi của kinh-doanh
     */
    public function getGadgets(): array
    {
        $all = $this->helper->getBusinessNews(20);
        return array_slice($all, 8, 4);
    }
}
