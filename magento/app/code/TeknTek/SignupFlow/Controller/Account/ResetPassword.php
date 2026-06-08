<?php

namespace TeknTek\SignupFlow\Controller\Account;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;

class ResetPassword extends Action implements HttpPostActionInterface
{
    private AccountManagementInterface $accountManagement;
    private CustomerRepositoryInterface $customerRepository;
    private CustomerSession $customerSession;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        AccountManagementInterface $accountManagement,
        CustomerRepositoryInterface $customerRepository
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->accountManagement = $accountManagement;
        $this->customerRepository = $customerRepository;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resetPasswordToken = (string)$this->getRequest()->getQuery('token');
        $customerId = (string)$this->getRequest()->getQuery('id');
        $password = (string)$this->getRequest()->getParam('password');
        $passwordConfirmation = (string)$this->getRequest()->getParam('password_confirmation');
        $email = null;
        $createPasswordParams = [
            'id' => $customerId,
            'token' => $resetPasswordToken,
        ];

        if ($password !== $passwordConfirmation) {
            $this->messageManager->addErrorMessage(__("New Password and Confirm New Password values didn't match."));
            $resultRedirect->setPath('customer/account/createPassword', $createPasswordParams);
            return $resultRedirect;
        }

        if ($password === '') {
            $this->messageManager->addErrorMessage(__('Please enter a new password.'));
            $resultRedirect->setPath('customer/account/createPassword', $createPasswordParams);
            return $resultRedirect;
        }

        if (!$this->isPasswordStrongEnough($password)) {
            $this->messageManager->addErrorMessage(__('Password must be at least 8 characters, start with an uppercase letter, and contain at least one number.'));
            $resultRedirect->setPath('customer/account/createPassword', $createPasswordParams);
            return $resultRedirect;
        }

        if ($customerId !== '') {
            try {
                $email = $this->customerRepository->getById((int)$customerId)->getEmail();
            } catch (\Throwable $throwable) {
                $email = null;
            }
        }

        try {
            $this->accountManagement->resetPassword($email, $resetPasswordToken, $password);

            if ($this->customerSession->isLoggedIn()) {
                $this->customerSession->logout();
                $this->customerSession->start();
            }

            $this->customerSession->unsRpToken();
            $this->customerSession->unsRpCustomerId();
            $this->messageManager->getMessages(true);
            $this->messageManager->addSuccessMessage(__('You updated your password.'));
            $resultRedirect->setPath('customer/account/login');
            return $resultRedirect;
        } catch (InputException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            foreach ($exception->getErrors() as $error) {
                $this->messageManager->addErrorMessage($error->getMessage());
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $throwable) {
            $this->messageManager->addErrorMessage(__('Something went wrong while saving the new password.'));
        }

        $resultRedirect->setPath('customer/account/createPassword', $createPasswordParams);
        return $resultRedirect;
    }

    private function isPasswordStrongEnough(string $password): bool
    {
        return strlen($password) >= 8 && preg_match('/^[A-Z]/', $password) === 1 && preg_match('/\d/', $password) === 1;
    }
}
