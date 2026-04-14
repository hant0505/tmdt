<?php

declare(strict_types=1);

namespace TeknTek\SignupFlow\Plugin\Customer;

use Magento\Customer\Controller\Account\ForgotPasswordPost;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\UrlInterface;

class ForgotPasswordRedirectPlugin
{
    public function __construct(
        private readonly ManagerInterface $messageManager,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function afterExecute(ForgotPasswordPost $subject, ResultInterface $result): ResultInterface
    {
        if (!$result instanceof Redirect) {
            return $result;
        }

        $messageCollection = $this->messageManager->getMessages(false);
        $errorMessages = $messageCollection->getItemsByType(MessageInterface::TYPE_ERROR);

        if (empty($errorMessages)) {
            $result->setUrl($this->urlBuilder->getUrl('customer/account/login'));
        }

        return $result;
    }
}
