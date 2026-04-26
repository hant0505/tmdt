<?php

namespace TeknTek\SignupFlow\Controller\Account;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use TeknTek\SignupFlow\Model\SignupSession;

class Login extends Action implements HttpPostActionInterface
{
    private JsonFactory $resultJsonFactory;
    private AccountManagementInterface $accountManagement;
    private CustomerSession $customerSession;
    private SignupSession $signupSession;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        AccountManagementInterface $accountManagement,
        CustomerSession $customerSession,
        SignupSession $signupSession
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->accountManagement = $accountManagement;
        $this->customerSession = $customerSession;
        $this->signupSession = $signupSession;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $loginData = (array)$this->getRequest()->getParam('login', []);
        $email = trim((string)($loginData['username'] ?? $this->getRequest()->getParam('email')));
        $password = (string)($loginData['password'] ?? $this->getRequest()->getParam('password'));

        if ($email === '' || $password === '') {
            return $result->setData([
                'success' => false,
                'message' => __('Please enter both email and password.'),
            ]);
        }

        try {
            $customer = $this->accountManagement->authenticate($email, $password);
            $this->customerSession->loginById((int)$customer->getId());
            $this->signupSession->clear();
            $this->customerSession->unsData('tekntek_login_error');

            return $result->setData([
                'success' => true,
                'message' => __('You are now signed in.'),
                'redirect_url' => $this->_url->getUrl('customer/account'),
            ]);
        } catch (LocalizedException $exception) {
            $this->customerSession->setData('tekntek_login_error', $exception->getMessage());

            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $throwable) {
            $this->customerSession->setData('tekntek_login_error', __('Unable to sign in right now. Please try again.'));

            return $result->setData([
                'success' => false,
                'message' => __('Unable to sign in right now. Please try again.'),
            ]);
        }
    }
}
