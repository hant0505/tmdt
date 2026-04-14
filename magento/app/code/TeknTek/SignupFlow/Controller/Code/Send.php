<?php

declare(strict_types=1);

namespace TeknTek\SignupFlow\Controller\Code;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use TeknTek\SignupFlow\Model\SignupSession;
use TeknTek\SignupFlow\Model\VerificationEmailSender;

class Send extends Action
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly SignupSession $signupSession,
        private readonly VerificationEmailSender $emailSender,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $isAjax = $this->getRequest()->isXmlHttpRequest();
        $result = $this->jsonFactory->create();

        try {
            if (!$this->getRequest()->isPost()) {
                throw new LocalizedException(__('Invalid request.'));
            }

            $email = trim((string)$this->getRequest()->getParam('email', ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new LocalizedException(__('Please enter a valid email address.'));
            }

            $websiteId = (int)$this->storeManager->getWebsite()->getId();
            try {
                $this->customerRepository->get($email, $websiteId);
                throw new LocalizedException(__('This email address is already registered. Please log in or use Forgot Password on the login page.'));
            } catch (NoSuchEntityException $exception) {
            }

            $issued = $this->signupSession->issueCode($email);
            $this->emailSender->send($email, $issued['code'], $issued['expires_at']);

            if (!$isAjax) {
                $this->messageManager->addSuccessMessage(__('We sent a 6-digit verification code to %1.', $email));
                return $this->resultRedirectFactory->create()->setPath('customer/account/create');
            }

            return $result->setData([
                'success' => true,
                'message' => __('We sent a 6-digit verification code to %1.', $email),
                'email' => $email,
                'expires_at' => $issued['expires_at'],
                'ttl_seconds' => $issued['ttl_seconds'],
            ]);
        } catch (LocalizedException $exception) {
            if (!$isAjax) {
                $this->messageManager->addErrorMessage($exception->getMessage());
                return $this->resultRedirectFactory->create()->setPath('customer/account/create');
            }

            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            if (!$isAjax) {
                $this->messageManager->addErrorMessage(__('Unable to send verification code right now. Please try again.'));
                return $this->resultRedirectFactory->create()->setPath('customer/account/create');
            }

            return $result->setData([
                'success' => false,
                'message' => __('Unable to send verification code right now. Please try again.'),
            ]);
        }
    }
}