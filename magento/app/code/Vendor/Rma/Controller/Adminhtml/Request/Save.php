<?php
namespace Vendor\Rma\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Vendor\Rma\Model\RmaFactory;

class Save extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Rma::rma';

    protected $rmaFactory;

    public function __construct(Action\Context $context, RmaFactory $rmaFactory)
    {
        $this->rmaFactory = $rmaFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($data) {
            $id = $this->getRequest()->getParam('entity_id');
            $model = $this->rmaFactory->create();

            if ($id) {
                $model->load($id);
            }

            // Chỉ cập nhật trạng thái
            $model->setStatus($data['status']);

            try {
                $model->save();
                $this->messageManager->addSuccessMessage(__('The RMA Request has been updated.'));
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }
        return $resultRedirect->setPath('*/*/');
    }
}