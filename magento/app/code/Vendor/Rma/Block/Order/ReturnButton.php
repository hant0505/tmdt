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

    public function getExistingRma()
    {
        $orderId = $this->getOrderId();
        if (!$orderId) {
            return null;
        }

        $collection = $this->rmaCollectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);
        // Lấy cái RMA mới nhất của đơn hàng này
        $collection->setOrder('created_at', 'DESC');
        
        // Trả về object RMA nếu có, ngược lại trả về false/null
        if ($collection->getSize() > 0) {
            return $collection->getFirstItem();
        }
        
        return null;
    }
}