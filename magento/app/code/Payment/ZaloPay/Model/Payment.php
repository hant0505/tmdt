<?php

declare(strict_types=1);

namespace Payment\ZaloPay\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order;

class Payment extends AbstractMethod
{
    public const CODE = 'zalopay';

    protected $_code = self::CODE;

    protected $_isOffline = false;

    protected $_canAuthorize = false;

    protected $_canCapture = false;

    protected $_canRefund = false;

    protected $_canVoid = false;

    protected $_isInitializeNeeded = true;

    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setData('state', Order::STATE_PENDING_PAYMENT);
        $stateObject->setData('status', Order::STATE_PENDING_PAYMENT);
        $stateObject->setData('is_notified', false);

        return $this;
    }
}
