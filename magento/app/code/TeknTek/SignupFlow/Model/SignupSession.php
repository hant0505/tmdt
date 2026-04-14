<?php

declare(strict_types=1);

namespace TeknTek\SignupFlow\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\DateTime;

class SignupSession
{
    private const KEY_EMAIL = 'tekntek_signup_email';
    private const KEY_CODE_HASH = 'tekntek_signup_code_hash';
    private const KEY_EXPIRES_AT = 'tekntek_signup_code_expires_at';
    private const KEY_VERIFIED = 'tekntek_signup_email_verified';

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly Random $random,
        private readonly DateTime $dateTime
    ) {
    }

    public function issueCode(string $email, int $ttlSeconds = 300): array
    {
        $code = (string)$this->random->getRandomNumber(100000, 999999);
        $expiresAt = $this->dateTime->gmtTimestamp() + $ttlSeconds;

        $this->customerSession->setData(self::KEY_EMAIL, $email);
        $this->customerSession->setData(self::KEY_CODE_HASH, hash('sha256', $code));
        $this->customerSession->setData(self::KEY_EXPIRES_AT, $expiresAt);
        $this->customerSession->setData(self::KEY_VERIFIED, false);

        return [
            'code' => $code,
            'expires_at' => $expiresAt,
            'ttl_seconds' => $ttlSeconds,
        ];
    }

    public function verifyCode(string $code): bool
    {
        if (!$this->hasPendingCode() || $this->isExpired()) {
            return false;
        }

        $expectedHash = (string)$this->customerSession->getData(self::KEY_CODE_HASH);
        if ($expectedHash === '' || !hash_equals($expectedHash, hash('sha256', $code))) {
            return false;
        }

        $this->customerSession->setData(self::KEY_VERIFIED, true);
        return true;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->hasPendingCode()
            && (bool)$this->customerSession->getData(self::KEY_VERIFIED)
            && !$this->isExpired();
    }

    public function getEmail(): string
    {
        return (string)$this->customerSession->getData(self::KEY_EMAIL);
    }

    public function getExpiresAt(): int
    {
        return (int)$this->customerSession->getData(self::KEY_EXPIRES_AT);
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->getExpiresAt();
        return $expiresAt > 0 && $this->dateTime->gmtTimestamp() > $expiresAt;
    }

    public function hasPendingCode(): bool
    {
        return $this->getEmail() !== '' && (string)$this->customerSession->getData(self::KEY_CODE_HASH) !== '';
    }

    public function clear(): void
    {
        $this->customerSession->unsData(self::KEY_EMAIL);
        $this->customerSession->unsData(self::KEY_CODE_HASH);
        $this->customerSession->unsData(self::KEY_EXPIRES_AT);
        $this->customerSession->unsData(self::KEY_VERIFIED);
    }
}