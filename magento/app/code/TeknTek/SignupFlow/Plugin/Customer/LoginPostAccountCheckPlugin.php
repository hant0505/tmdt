<?php

declare(strict_types=1);

namespace TeknTek\SignupFlow\Plugin\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Controller\Account\LoginPost;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class LoginPostAccountCheckPlugin
{
    private const LOGIN_ERROR_KEY = 'tekntek_login_error';

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerSession $customerSession,
        private readonly ManagerInterface $messageManager,
        private readonly RedirectFactory $resultRedirectFactory
    ) {
    }

    /**
     * Show a specific error when users try to log in with an email that does not exist.
     */
    public function aroundExecute(LoginPost $subject, callable $proceed)
    {
        if (!$subject->getRequest()->isPost()) {
            return $proceed();
        }

        $loginData = (array)$subject->getRequest()->getPost('login');
        $email = trim((string)($loginData['username'] ?? ''));
        $password = (string)($loginData['password'] ?? '');

        if ($email === '' || $password === '') {
            return $proceed();
        }

        try {
            $websiteId = (int)$this->storeManager->getWebsite()->getId();
            $this->customerRepository->get($email, $websiteId);
        } catch (NoSuchEntityException $exception) {
            $this->customerSession->setData(self::LOGIN_ERROR_KEY, (string)__('This account does not exist.'));

            /** @var Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('customer/account/login');
        }

        $result = $proceed();

        // Persist a login error in session so the custom template can always render it.
        if (!$this->customerSession->isLoggedIn()) {
            $errorMessage = '';
            $messages = $this->messageManager->getMessages(false)->getItems();
            foreach ($messages as $message) {
                if ((string)$message->getType() === 'error') {
                    $errorMessage = trim((string)$message->getText());
                    break;
                }
            }

            if ($errorMessage === '') {
                $errorMessage = (string)__('Incorrect password. Please try again.');
            }

            $this->customerSession->setData(self::LOGIN_ERROR_KEY, $errorMessage);
        }

        return $result;
    }
}
