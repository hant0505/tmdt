<?php

declare(strict_types=1);

namespace TeknTek\SignupFlow\Model;

use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;

class VerificationEmailSender
{
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function send(string $email, string $code, int $expiresAt): void
    {
        $store = $this->storeManager->getStore();
        $transport = $this->transportBuilder
            ->setTemplateIdentifier('tekntek_signup_verification')
            ->setTemplateOptions([
                'area' => Area::AREA_FRONTEND,
                'store' => (int)$store->getId(),
            ])
            ->setTemplateVars([
                'code' => $code,
                'expires_minutes' => 5,
                'expires_at' => $expiresAt,
                'store_name' => (string)$store->getName(),
            ])
            ->setFromByScope('general', (int)$store->getId())
            ->addTo($email)
            ->getTransport();

        $transport->sendMessage();
    }
}