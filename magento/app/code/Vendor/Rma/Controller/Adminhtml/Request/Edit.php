<?php
namespace Vendor\Rma\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Rma::rma';

    protected $resultPageFactory;

    public function __construct(Action\Context $context, PageFactory $resultPageFactory)
    {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Vendor_Rma::rma');
        $resultPage->getConfig()->getTitle()->prepend(__('Edit RMA Request'));
        return $resultPage;
    }
}