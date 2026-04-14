<?php

declare(strict_types=1);

namespace TeknTek\GoogleLogin\Controller\Auth;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Math\Random;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use TeknTek\GoogleLogin\Model\Config;
use TeknTek\GoogleLogin\Model\OAuthClient;

class Google extends Action
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly OAuthClient $oAuthClient,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerSession $customerSession,
        private readonly Random $random
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $websiteId = (int)$this->storeManager->getWebsite()->getId();

        if (!$this->config->isEnabled($websiteId)) {
            $this->messageManager->addErrorMessage(__('Google login is disabled.'));
            return $resultRedirect->setPath('customer/account/login');
        }

        $clientId = $this->config->getClientId($websiteId);
        $clientSecret = $this->config->getClientSecret($websiteId);
        if ($clientId === '' || $clientSecret === '') {
            $this->messageManager->addErrorMessage(__('Google login is not configured.'));
            return $resultRedirect->setPath('customer/account/login');
        }

        $state = $this->random->getRandomString(32);
        $this->customerSession->setData('tekntek_google_state', $state);

        $redirectUri = $this->_url->getUrl(
            'tekntek_googlelogin/auth/callback',
            ['_secure' => $this->getRequest()->isSecure()]
        );
        $authUrl = $this->oAuthClient->buildAuthUrl($clientId, $redirectUri, $state);

        return $resultRedirect->setUrl($authUrl);
    }
}
