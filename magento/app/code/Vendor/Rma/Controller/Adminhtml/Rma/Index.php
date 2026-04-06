<?php
namespace Vendor\Rma\Controller\Adminhtml\Rma;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    protected $resultPageFactory;

    public function __construct(
        Action\Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Vendor_Rma::rma_manage');
        $resultPage->getConfig()->getTitle()->prepend(__('Manage Returns'));
        return $resultPage;
    }
}