<?php

declare(strict_types=1);

namespace TeknTek\SignupFlow\Controller\Code;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use TeknTek\SignupFlow\Model\SignupSession;

class Verify extends Action
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly SignupSession $signupSession
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $isAjax = $this->getRequest()->isXmlHttpRequest();
        $result = $this->jsonFactory->create();

        try {
            if (!$this->getRequest()->isPost()) {
                throw new LocalizedException(__('Invalid request.'));
            }

            $code = preg_replace('/\D+/', '', (string)$this->getRequest()->getParam('code', ''));
            if (strlen($code) !== 6) {
                throw new LocalizedException(__('Please enter the 6-digit code.'));
            }

            if (!$this->signupSession->verifyCode($code)) {
                throw new LocalizedException(__('The code is invalid or has expired.'));
            }

            if (!$isAjax) {
                $this->messageManager->addSuccessMessage(__('Email verified successfully.'));
                return $this->resultRedirectFactory->create()->setPath('customer/account/create');
            }

            return $result->setData([
                'success' => true,
                'message' => __('Email verified successfully.'),
                'email' => $this->signupSession->getEmail(),
            ]);
        } catch (LocalizedException $exception) {
            if (!$isAjax) {
                $this->messageManager->addErrorMessage($exception->getMessage());
                return $this->resultRedirectFactory->create()->setPath('customer/account/create');
            }

            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            if (!$isAjax) {
                $this->messageManager->addErrorMessage(__('Unable to verify the code right now. Please try again.'));
                return $this->resultRedirectFactory->create()->setPath('customer/account/create');
            }

            return $result->setData([
                'success' => false,
                'message' => __('Unable to verify the code right now. Please try again.'),
            ]);
        }
    }
}