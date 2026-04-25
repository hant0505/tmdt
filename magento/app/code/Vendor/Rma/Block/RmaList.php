<?php
namespace Vendor\Rma\Block;

use Magento\Framework\View\Element\Template;
use Vendor\Rma\Model\ResourceModel\Rma\CollectionFactory;
use Magento\Customer\Model\Session;

class RmaList extends Template
{
    protected $rmaCollectionFactory;
    protected $customerSession;

    public function __construct(
        Template\Context $context,
        CollectionFactory $rmaCollectionFactory,
        Session $customerSession,
        array $data = []
    ) {
        $this->rmaCollectionFactory = $rmaCollectionFactory;
        $this->customerSession = $customerSession;
        parent::__construct($context, $data);
    }

    public function getRmaCollection()
    {
        $customerId = $this->customerSession->getCustomerId();
        $collection = $this->rmaCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->setOrder('created_at', 'DESC');
        
        return $collection;
    }
}