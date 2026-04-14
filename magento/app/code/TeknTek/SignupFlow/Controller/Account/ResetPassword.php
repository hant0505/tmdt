<?php

declare(strict_types=1);

namespace TeknTek\SignupFlow\Controller\Account;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\InputException;

class ResetPassword extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerSession $customerSession
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $token = (string)$this->getRequest()->getQuery('token');
        $customerId = (string)$this->getRequest()->getQuery('id');
        $password = (string)$this->getRequest()->getPost('password');
        $passwordConfirmation = (string)$this->getRequest()->getPost('password_confirmation');

        if ($password !== $passwordConfirmation) {
            $this->messageManager->addErrorMessage(__("New Password and Confirm New Password values did not match."));
            return $resultRedirect->setPath('customer/account/createPassword', ['token' => $token]);
        }

        if (iconv_strlen($password) <= 0) {
            $this->messageManager->addErrorMessage(__('Please enter a new password.'));
            return $resultRedirect->setPath('customer/account/createPassword', ['token' => $token]);
        }

        if (!$this->isPasswordRuleValid($password)) {
            $this->messageManager->addErrorMessage(
                __('Password must be at least 8 characters, start with an uppercase letter, and contain at least one number.')
            );
            return $resultRedirect->setPath('customer/account/createPassword', ['token' => $token]);
        }

        try {
            $email = null;
            if ($customerId !== '') {
                $email = $this->customerRepository->getById($customerId)->getEmail();
            }

            $this->accountManagement->resetPassword($email, $token, $password);

            if ($this->customerSession->isLoggedIn()) {
                $this->customerSession->logout();
                $this->customerSession->start();
            }

            $this->customerSession->unsRpToken();
            $this->customerSession->unsRpCustomerId();
            $this->messageManager->addSuccessMessage(__('You updated your password.'));

            return $resultRedirect->setPath('customer/account/login');
        } catch (InputException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            foreach ($exception->getErrors() as $error) {
                $this->messageManager->addErrorMessage($error->getMessage());
            }
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Something went wrong while saving the new password.'));
        }

        return $resultRedirect->setPath('customer/account/createPassword', ['token' => $token]);
    }

    private function isPasswordRuleValid(string $password): bool
    {
        return strlen($password) >= 8
            && (bool)preg_match('/^[A-Z]/', $password)
            && (bool)preg_match('/\d/', $password);
    }
}
