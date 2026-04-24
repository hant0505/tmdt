<?php

namespace TeknTek\SignupFlow\Model;

class SignupSession extends \Magento\Framework\Session\Generic
{
    private const KEY_EMAIL = 'tekntek_signup_email';
    private const KEY_CODE = 'tekntek_signup_code';
    private const KEY_EXPIRES_AT = 'tekntek_signup_expires_at';
    private const KEY_VERIFIED = 'tekntek_signup_verified';

    public function setEmail(?string $email): self
    {
        $this->setData(self::KEY_EMAIL, $this->normalize($email));
        return $this;
    }

    public function getEmail(): string
    {
        return $this->normalize((string)$this->getData(self::KEY_EMAIL));
    }

    public function setCode(?string $code): self
    {
        $this->setData(self::KEY_CODE, $this->normalize($code));
        return $this;
    }

    public function getCode(): string
    {
        return $this->normalize((string)$this->getData(self::KEY_CODE));
    }

    public function setExpiresAt(?int $expiresAt): self
    {
        $this->setData(self::KEY_EXPIRES_AT, $expiresAt ? (int)$expiresAt : 0);
        return $this;
    }

    public function getExpiresAt(): int
    {
        return (int)$this->getData(self::KEY_EXPIRES_AT);
    }

    public function setVerifiedEmail(bool $verified): self
    {
        $this->setData(self::KEY_VERIFIED, $verified ? 1 : 0);
        return $this;
    }

    public function hasVerifiedEmail(): bool
    {
        return (bool)$this->getData(self::KEY_VERIFIED);
    }

    public function hasPendingCode(): bool
    {
        return $this->getCode() !== '' && !$this->hasVerifiedEmail() && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->getExpiresAt();
        return $expiresAt > 0 && $expiresAt <= time();
    }

    public function clear(): self
    {
        $this->unsData(self::KEY_EMAIL);
        $this->unsData(self::KEY_CODE);
        $this->unsData(self::KEY_EXPIRES_AT);
        $this->unsData(self::KEY_VERIFIED);
        return $this;
    }

    private function normalize(?string $value): string
    {
        return trim((string)$value);
    }
}
