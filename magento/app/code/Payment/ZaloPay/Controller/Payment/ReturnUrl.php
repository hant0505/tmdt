<?php

declare(strict_types=1);

namespace Payment\ZaloPay\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Sales\Model\Order;

class ReturnUrl extends Action implements HttpGetActionInterface
{
    private CheckoutSession $checkoutSession;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $order = $this->checkoutSession->getLastRealOrder();

        if ($order && in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true)) {
            return $resultRedirect->setPath('checkout/onepage/success');
        }

        $this->messageManager->addNoticeMessage(
            __('ZaloPay Sandbox payment is waiting for verified server callback.')
        );

        return $resultRedirect->setPath('checkout/onepage/failure');
    }
}
