<?php
namespace Vendor\Rma\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    // Xác thực quyền ACL đã tạo ở bước 1
    const ADMIN_RESOURCE = 'Vendor_Rma::rma';

    protected $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Vendor_Rma::rma');
        $resultPage->getConfig()->getTitle()->prepend(__('RMA Requests'));
        
        return $resultPage;
    }
}