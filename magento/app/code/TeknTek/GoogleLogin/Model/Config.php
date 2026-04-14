<?php

declare(strict_types=1);

namespace TeknTek\GoogleLogin\Model;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'tekntek_googlelogin/general/enabled';
    private const XML_PATH_CLIENT_ID = 'tekntek_googlelogin/general/client_id';
    private const XML_PATH_CLIENT_SECRET = 'tekntek_googlelogin/general/client_secret';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    public function getClientId(?int $websiteId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_CLIENT_ID,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    public function getClientSecret(?int $websiteId = null): string
    {
        $encrypted = (string)$this->scopeConfig->getValue(
            self::XML_PATH_CLIENT_SECRET,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );

        return $encrypted === '' ? '' : (string)$this->encryptor->decrypt($encrypted);
    }
}
