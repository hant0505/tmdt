<?php
namespace Vendor\Rma\Controller\Request;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Vendor\Rma\Model\RmaFactory;
use Magento\Customer\Model\Session;
use Vendor\Rma\Model\ResourceModel\Rma\CollectionFactory; // Thêm thư viện này

class Save implements HttpPostActionInterface
{
    protected $request;
    protected $resultFactory;
    protected $messageManager;
    protected $rmaFactory;
    protected $customerSession;
    protected $rmaCollectionFactory;

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        ManagerInterface $messageManager,
        RmaFactory $rmaFactory,
        Session $customerSession,
        CollectionFactory $rmaCollectionFactory // Inject vào đây
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->messageManager = $messageManager;
        $this->rmaFactory = $rmaFactory;
        $this->customerSession = $customerSession;
        $this->rmaCollectionFactory = $rmaCollectionFactory;
    }

    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        
        if (!$this->customerSession->isLoggedIn()) {
            $this->messageManager->addErrorMessage(__('Please log in first.'));
            return $resultRedirect->setPath('customer/account/login');
        }

        $postData = $this->request->getPostValue();
        $orderId = $postData['order_id'] ?? null;
        
        if (empty($orderId) || empty($postData['reason'])) {
            $this->messageManager->addErrorMessage(__('Invalid return request data.'));
            return $resultRedirect->setPath('*/*/index', ['order_id' => $orderId]);
        }

        // --- BẮT ĐẦU ĐOẠN CHECK NGHIỆP VỤ ---
        $existingRma = $this->rmaCollectionFactory->create()
            ->addFieldToFilter('order_id', $orderId)
            ->addFieldToFilter('customer_id', $this->customerSession->getCustomerId());

        if ($existingRma->getSize() > 0) {
            $this->messageManager->addErrorMessage(__('A return request already exists for this order.'));
            return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
        }
        // --- KẾT THÚC ĐOẠN CHECK NGHIỆP VỤ ---

        try {
            $rmaModel = $this->rmaFactory->create();
            $rmaModel->setOrderId($orderId);
            $rmaModel->setCustomerId($this->customerSession->getCustomerId());
            $rmaModel->setReason($postData['reason']);
            $rmaModel->setCustomerComment($postData['customer_comment'] ?? '');
            $rmaModel->setStatus('pending');
            
            $incrementId = 'RMA-' . date('YmdHis') . '-' . rand(10, 99);
            $rmaModel->setIncrementId($incrementId);

            $rmaModel->save();

            $this->messageManager->addSuccessMessage(__('Your return request %1 has been submitted successfully.', $incrementId));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Something went wrong: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('rma/index/index');
    }
}