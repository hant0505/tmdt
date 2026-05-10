<?php

namespace TeknTek\SignupFlow\Controller\Account;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Controller\Account\ForgotPasswordPost as CoreForgotPasswordPost;
use Magento\Customer\Model\AccountManagement;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\SecurityViolationException;
use Magento\Framework\Validator\EmailAddress;
use Magento\Framework\Validator\ValidatorChain;

class ForgotPasswordPost extends CoreForgotPasswordPost
{
    public function __construct(
        Context $context,
        Session $customerSession,
        AccountManagementInterface $customerAccountManagement,
        Escaper $escaper
    ) {
        parent::__construct($context, $customerSession, $customerAccountManagement, $escaper);
    }

    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $email = trim((string)$this->getRequest()->getPost('email'));

        if ($email === '') {
            $this->messageManager->addErrorMessage(__('Please enter your email.'));
            return $resultRedirect->setPath('*/*/forgotpassword');
        }

        if (!ValidatorChain::is($email, EmailAddress::class)) {
            $this->session->setForgottenEmail($email);
            $this->messageManager->addErrorMessage(
                __('The email address is incorrect. Verify the email address and try again.')
            );

            return $resultRedirect->setPath('*/*/forgotpassword');
        }

        try {
            $this->customerAccountManagement->initiatePasswordReset(
                $email,
                AccountManagement::EMAIL_RESET
            );
        } catch (NoSuchEntityException $exception) {
        } catch (SecurityViolationException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            return $resultRedirect->setPath('*/*/forgotpassword');
        } catch (\Exception $exception) {
            $this->messageManager->addExceptionMessage(
                $exception,
                __('We\'re unable to send the password reset email.')
            );
            return $resultRedirect->setPath('*/*/forgotpassword');
        }

        $this->messageManager->getMessages(true);
        $this->session->destroy(['send_expire_cookie']);
        $this->messageManager->addSuccessMessage($this->getSuccessMessage($email));

        return $resultRedirect->setPath('customer/account/login');
    }
}
