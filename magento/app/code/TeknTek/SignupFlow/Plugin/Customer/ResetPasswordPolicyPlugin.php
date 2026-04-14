<?php

declare(strict_types=1);

namespace TeknTek\SignupFlow\Plugin\Customer;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Controller\Account\ResetPasswordPost;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;

class ResetPasswordPolicyPlugin
{
    public function __construct(
        private readonly ManagerInterface $messageManager,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerSession $customerSession,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function aroundExecute(ResetPasswordPost $subject, callable $proceed)
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $password = (string)$subject->getRequest()->getPost('password');
        $passwordConfirmation = (string)$subject->getRequest()->getPost('password_confirmation');
        $token = (string)$subject->getRequest()->getQuery('token');
        $customerId = (string)$subject->getRequest()->getQuery('id');
        $email = null;

        if ($password !== $passwordConfirmation) {
            $this->messageManager->addErrorMessage(__("New Password and Confirm New Password values did not match."));
            $resultRedirect->setPath('*/*/createPassword', ['token' => $token]);

            return $resultRedirect;
        }

        if (iconv_strlen($password) <= 0) {
            $this->messageManager->addErrorMessage(__('Please enter a new password.'));
            $resultRedirect->setPath('*/*/createPassword', ['token' => $token]);

            return $resultRedirect;
        }

        if (!$this->isPasswordRuleValid($password)) {
            $this->messageManager->addErrorMessage(
                __('Password must be at least 8 characters, start with an uppercase letter, and contain at least one number.')
            );

            $resultRedirect->setPath('*/*/createPassword', ['token' => $token]);

            return $resultRedirect;
        }

        try {
            if ($customerId) {
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

            $resultRedirect->setUrl($this->urlBuilder->getUrl('customer/account/login'));

            return $resultRedirect;
        } catch (InputException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            foreach ($exception->getErrors() as $error) {
                $this->messageManager->addErrorMessage($error->getMessage());
            }
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Something went wrong while saving the new password.'));
        }

        $resultRedirect->setPath('*/*/createPassword', ['token' => $token]);

        return $resultRedirect;
    }

    private function isPasswordRuleValid(string $password): bool
    {
        return strlen($password) >= 8
            && (bool)preg_match('/^[A-Z]/', $password)
            && (bool)preg_match('/\d/', $password);
    }
}
