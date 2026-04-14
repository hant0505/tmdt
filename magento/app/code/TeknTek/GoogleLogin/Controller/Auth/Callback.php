<?php

declare(strict_types=1);

namespace TeknTek\GoogleLogin\Controller\Auth;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Math\Random;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use TeknTek\GoogleLogin\Model\Config;
use TeknTek\GoogleLogin\Model\OAuthClient;

class Callback extends Action
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly OAuthClient $oAuthClient,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerFactory $customerFactory,
        private readonly CustomerSession $customerSession,
        private readonly Random $random,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $websiteId = (int)$this->storeManager->getWebsite()->getId();
            if (!$this->config->isEnabled($websiteId)) {
                throw new LocalizedException(__('Google login is disabled.'));
            }

            $oauthError = (string)$this->getRequest()->getParam('error', '');
            if ($oauthError !== '') {
                throw new LocalizedException(__('Google authorization failed: %1', $oauthError));
            }

            $code = (string)$this->getRequest()->getParam('code', '');
            $state = (string)$this->getRequest()->getParam('state', '');

            if ($code === '' || $state === '') {
                throw new LocalizedException(
                    __('Google callback is missing required data. Please start again from "Login with Google account" on the login page.')
                );
            }

            $expectedState = (string)$this->customerSession->getData('tekntek_google_state');
            $this->customerSession->unsetData('tekntek_google_state');

            if ($expectedState === '' || !hash_equals($expectedState, $state)) {
                throw new LocalizedException(__('Invalid Google login state.'));
            }

            $redirectUri = $this->_url->getUrl(
                'tekntek_googlelogin/auth/callback',
                ['_secure' => $this->getRequest()->isSecure()]
            );
            $accessToken = $this->oAuthClient->fetchAccessToken(
                $this->config->getClientId($websiteId),
                $this->config->getClientSecret($websiteId),
                $redirectUri,
                $code
            );
            $userInfo = $this->oAuthClient->fetchUserInfo($accessToken);

            $email = (string)($userInfo['email'] ?? '');
            $isVerified = (bool)($userInfo['email_verified'] ?? false);
            if ($email === '' || !$isVerified) {
                throw new LocalizedException(__('Google account email is not verified.'));
            }

            try {
                $customer = $this->customerRepository->get($email, $websiteId);
            } catch (NoSuchEntityException) {
                $newCustomer = $this->customerFactory->create();
                $newCustomer->setWebsiteId($websiteId);
                $newCustomer->setStoreId((int)$this->storeManager->getStore()->getId());
                $newCustomer->setEmail($email);
                $newCustomer->setFirstname((string)($userInfo['given_name'] ?? 'Google'));
                $newCustomer->setLastname((string)($userInfo['family_name'] ?? 'User'));

                $randomPassword = $this->random->getRandomString(32);
                $customer = $this->accountManagement->createAccount($newCustomer->getDataModel(), $randomPassword);
            }

            // Use customer model login to ensure Magento session is fully initialized.
            $customerModel = $this->customerFactory->create();
            $customerModel->setWebsiteId($websiteId);
            $customerModel->loadByEmail((string)$customer->getEmail());
            if (!$customerModel->getId()) {
                throw new LocalizedException(__('Unable to initialize customer session.'));
            }

            $this->customerSession->setCustomerAsLoggedIn($customerModel);
            $this->customerSession->regenerateId();
            $this->messageManager->addSuccessMessage(__('You are now signed in with Google.'));
            return $resultRedirect->setPath('customer/account');
        } catch (LocalizedException $e) {
            $this->logger->warning('Google login rejected: ' . $e->getMessage(), [
                'uri' => (string)$this->getRequest()->getRequestUri(),
                'has_code' => $this->getRequest()->getParam('code') !== null,
                'has_state' => $this->getRequest()->getParam('state') !== null,
                'oauth_error' => (string)$this->getRequest()->getParam('error', '')
            ]);
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__('Google login failed. Please try again.'));
        }

        return $resultRedirect->setPath('customer/account/login');
    }
}
