<?php

declare(strict_types=1);

namespace TeknTek\GoogleLogin\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;

class OAuthClient
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function __construct(private readonly Curl $curl)
    {
    }

    public function buildAuthUrl(string $clientId, string $redirectUri, string $state): string
    {
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
            'access_type' => 'online'
        ]);

        return self::AUTH_URL . '?' . $query;
    }

    public function fetchAccessToken(string $clientId, string $clientSecret, string $redirectUri, string $code): string
    {
        $this->curl->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->curl->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ]);

        $data = json_decode($this->curl->getBody(), true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new LocalizedException(__('Google token exchange failed.'));
        }

        return (string)$data['access_token'];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchUserInfo(string $accessToken): array
    {
        $this->curl->addHeader('Authorization', 'Bearer ' . $accessToken);
        $this->curl->get(self::USERINFO_URL);

        $data = json_decode($this->curl->getBody(), true);
        if (!is_array($data) || empty($data['email'])) {
            throw new LocalizedException(__('Google user info is invalid.'));
        }

        return $data;
    }
}
