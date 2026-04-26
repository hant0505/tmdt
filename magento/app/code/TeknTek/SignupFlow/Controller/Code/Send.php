<?php

namespace TeknTek\SignupFlow\Controller\Code;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
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
}
