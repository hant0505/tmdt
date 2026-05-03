<?php

namespace TeknTek\SignupFlow\Controller\Code;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use TeknTek\SignupFlow\Model\SignupSession;

class Verify extends Action implements HttpPostActionInterface
{
    private JsonFactory $resultJsonFactory;
    private SignupSession $signupSession;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        SignupSession $signupSession
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->signupSession = $signupSession;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $code = preg_replace('/\D+/', '', (string)$this->getRequest()->getParam('code'));
        $sessionCode = $this->signupSession->getCode();
        $email = $this->signupSession->getEmail();

        if ($email === '') {
            return $result->setData([
                'success' => false,
                'message' => __('Please request a verification code first.'),
            ]);
        }

        if ($this->signupSession->isExpired() || $sessionCode === '') {
            $this->signupSession->clear();

            return $result->setData([
                'success' => false,
                'message' => __('The verification code has expired. Please request a new one.'),
            ]);
        }

        if ($code === '' || $code !== $sessionCode) {
            return $result->setData([
                'success' => false,
                'message' => __('The verification code is invalid.'),
            ]);
        }

        $this->signupSession
            ->setVerifiedEmail(true)
            ->setCode(null)
            ->setExpiresAt(0);

        return $result->setData([
            'success' => true,
            'message' => __('Email verified successfully.'),
            'email' => $email,
        ]);
    }
}
