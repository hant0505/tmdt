<?php

declare(strict_types=1);

namespace TeknTek\SignupFlow\Controller\Account;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use TeknTek\SignupFlow\Model\SignupSession;

class Create extends Action
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly SignupSession $signupSession,
        private readonly CustomerFactory $customerFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerSession $customerSession,
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

            if (!$this->signupSession->hasVerifiedEmail()) {
                throw new LocalizedException(__('Please verify your email address first.'));
            }

            $email = $this->signupSession->getEmail();
            $firstName = trim((string)$this->getRequest()->getParam('firstname', ''));
            $lastName = trim((string)$this->getRequest()->getParam('lastname', ''));
            $password = (string)$this->getRequest()->getParam('password', '');
            $passwordConfirmation = (string)$this->getRequest()->getParam('password_confirmation', '');
            $prefix = trim((string)$this->getRequest()->getParam('prefix', ''));

            if ($firstName === '' || $lastName === '') {
                throw new LocalizedException(__('Please fill in your name details.'));
            }

            if ($password === '' || $password !== $passwordConfirmation) {
                throw new LocalizedException(__('Please enter matching passwords.'));
            }

            if (!$this->isPasswordRuleValid($password)) {
                throw new LocalizedException(__('Password must be at least 8 characters, start with an uppercase letter, and contain at least one number.'));
            }

            $websiteId = (int)$this->storeManager->getWebsite()->getId();
            try {
                $this->customerRepository->get($email, $websiteId);
                throw new LocalizedException(__('This email address is already registered.'));
            } catch (NoSuchEntityException $exception) {
            }

            $customer = $this->customerFactory->create();
            $customer->setWebsiteId($websiteId);
            $customer->setStoreId((int)$this->storeManager->getStore()->getId());
            $customer->setEmail($email);
            $customer->setFirstname($firstName);
            $customer->setLastname($lastName);

            if ($prefix !== '') {
                $customer->setPrefix($prefix);
            }

            $customerEntity = $this->accountManagement->createAccount($customer->getDataModel(), $password);

            $customerModel = $this->customerFactory->create();
            $customerModel->setWebsiteId($websiteId);
            $customerModel->loadByEmail((string)$customerEntity->getEmail());
            if (!$customerModel->getId()) {
                throw new LocalizedException(__('Unable to initialize customer session.'));
            }

            // Force activation for this custom verified-email signup flow.
            if ($customerModel->getConfirmation()) {
                $customerModel->setConfirmation(null);
                $customerModel->save();
            }

            $this->customerSession->setCustomerAsLoggedIn($customerModel);
            $this->customerSession->regenerateId();
            $this->signupSession->clear();

            if (!$isAjax) {
                return $this->resultRedirectFactory->create()->setUrl($this->_url->getBaseUrl());
            }

            return $result->setData([
                'success' => true,
                'redirect_url' => $this->_url->getBaseUrl(),
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
                $this->messageManager->addErrorMessage(__('Unable to create your account right now. Please try again.'));
                return $this->resultRedirectFactory->create()->setPath('customer/account/create');
            }

            return $result->setData([
                'success' => false,
                'message' => __('Unable to create your account right now. Please try again.'),
            ]);
        }
    }

    private function isPasswordRuleValid(string $password): bool
    {
        return strlen($password) >= 8
            && (bool)preg_match('/^[A-Z]/', $password)
            && (bool)preg_match('/\d/', $password);
    }
}