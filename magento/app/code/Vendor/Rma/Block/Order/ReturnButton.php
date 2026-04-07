<?php
namespace Vendor\Rma\Block\Order;

use Magento\Framework\View\Element\Template;
use Vendor\Rma\Model\ResourceModel\Rma\CollectionFactory; // Thêm thư viện

class ReturnButton extends Template
{
    protected $rmaCollectionFactory;

    public function __construct(
        Template\Context $context,
        CollectionFactory $rmaCollectionFactory, // Inject vào
        array $data = []
    ) {
        $this->rmaCollectionFactory = $rmaCollectionFactory;
        parent::__construct($context, $data);
    }

    public function getReturnUrl()
    {
        return $this->getUrl('rma/request/index', ['order_id' => $this->getOrderId()]);
    }

    public function getOrderId()
    {
        return $this->getRequest()->getParam('order_id');
    }

    // Viết hàm check xem Order này có RMA chưa
    public function hasExistingRma()
    {
        $orderId = $this->getOrderId();
        if (!$orderId) return false;

        $collection = $this->rmaCollectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);
        
        return $collection->getSize() > 0;
    }
}