<?php

declare(strict_types=1);

namespace Payment\ZaloPay\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect as ResultRedirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payment\ZaloPay\Helper\Data as ZaloPayHelper;
use Payment\ZaloPay\Model\Payment as ZaloPayPayment;

class Redirect extends Action implements HttpGetActionInterface
{
    private CheckoutSession $checkoutSession;

    private ZaloPayHelper $zaloPayHelper;

    private OrderRepositoryInterface $orderRepository;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        ZaloPayHelper $zaloPayHelper,
        OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->zaloPayHelper = $zaloPayHelper;
        $this->orderRepository = $orderRepository;
    }

    public function execute(): ResultRedirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order || !$order->getId() || $order->getPayment()->getMethod() !== ZaloPayPayment::CODE) {
            return $resultRedirect->setPath('checkout/cart');
        }

        try {
            $response = $this->zaloPayHelper->createZaloPayOrder($order);

            return $resultRedirect->setUrl((string)$response['order_url']);
        } catch (LocalizedException $exception) {
            $message = $exception->getMessage();
        } catch (\Throwable $exception) {
            $message = __('Unable to create ZaloPay Sandbox order.')->render();
            $this->zaloPayHelper->debug('Create order exception', ['order_id' => $order->getId()]);
        }

        $order->addCommentToStatusHistory('ZaloPay Sandbox create order failed: ' . $message);
        $this->orderRepository->save($order);
        $this->checkoutSession->restoreQuote();
        $this->messageManager->addErrorMessage($message);

        return $resultRedirect->setPath('checkout/cart');
    }
}
