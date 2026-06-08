<?php

namespace TeknTek\SignupFlow\Controller\Code;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use TeknTek\SignupFlow\Model\SignupSession;

class Send extends Action implements HttpPostActionInterface
{
    private const EXPIRES_SECONDS = 300;

    private JsonFactory $resultJsonFactory;
    private SignupSession $signupSession;
    private TransportBuilder $transportBuilder;
    private StateInterface $inlineTranslation;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        SignupSession $signupSession,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->signupSession = $signupSession;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $email = strtolower(trim((string)$this->getRequest()->getParam('email')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $result->setData([
                'success' => false,
                'message' => __('Please enter a valid email address.'),
            ]);
        }

        $code = (string)random_int(100000, 999999);
        $expiresAt = time() + self::EXPIRES_SECONDS;

        $this->signupSession
            ->setEmail($email)
            ->setCode($code)
            ->setExpiresAt($expiresAt)
            ->setVerifiedEmail(false);

        $this->inlineTranslation->suspend();

        try {
            if ($this->getEnvValue('RESEND_API_KEY') !== '') {
                $this->sendViaResend($email, $code);
            } else {
                $this->sendViaMagentoTransport($email, $code);
            }
        } catch (\Throwable $throwable) {
            $this->signupSession->clear();
            ObjectManager::getInstance()->get(LoggerInterface::class)->error(
                'TeknTek signup OTP email failed.',
                [
                    'email' => $email,
                    'exception_message' => $throwable->getMessage(),
                    'exception_class' => get_class($throwable),
                ]
            );

            return $result->setData([
                'success' => false,
                'message' => __('Unable to send verification code right now. Please try again.'),
            ]);
        } finally {
            $this->inlineTranslation->resume();
        }

        return $result->setData([
            'success' => true,
            'message' => __('We sent a 6-digit verification code.'),
            'email' => $email,
            'expires_at' => $expiresAt,
        ]);
    }

    private function sendViaMagentoTransport(string $email, string $code): void
    {
        $store = $this->storeManager->getStore();

        $transport = $this->transportBuilder
            ->setTemplateIdentifier('tekntek_signup_code')
            ->setTemplateOptions([
                'area' => 'frontend',
                'store' => (int)$store->getId(),
            ])
            ->setTemplateVars([
                'code' => $code,
                'expires_minutes' => (int)(self::EXPIRES_SECONDS / 60),
                'email' => $email,
                'store' => $store,
            ])
            ->setFromByScope('general')
            ->addTo($email)
            ->getTransport();

        $transport->sendMessage();
    }

    private function sendViaResend(string $email, string $code): void
    {
        $apiKey = $this->getEnvValue('RESEND_API_KEY');
        $fromEmail = $this->getEnvValue('RESEND_FROM_EMAIL') ?: 'onboarding@resend.dev';
        $expiresMinutes = (int)(self::EXPIRES_SECONDS / 60);

        $payload = [
            'from' => $fromEmail,
            'to' => [$email],
            'subject' => 'Your verification code',
            'html' => sprintf(
                '<p>Hello,</p><p>Your verification code is <strong>%s</strong>.</p><p>This code expires in %d minutes.</p><p>If you did not request this code, you can ignore this email.</p>',
                htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
                $expiresMinutes
            ),
        ];

        $handle = curl_init('https://api.resend.com/emails');
        if ($handle === false) {
            throw new \RuntimeException('Unable to initialize Resend request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $responseBody = curl_exec($handle);
        $statusCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($responseBody === false || $statusCode < 200 || $statusCode >= 300) {
            $message = $error !== '' ? $error : (string)$responseBody;
            throw new \RuntimeException('Resend API failed with HTTP ' . $statusCode . ': ' . $message);
        }
    }

    private function getEnvValue(string $key): string
    {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        if ($value === false && isset($_SERVER[$key])) {
            $value = $_SERVER[$key];
        }

        return trim((string)$value);
    }
}
