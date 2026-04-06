<?php
namespace Vendor\Rma\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class Status implements ArrayInterface
{
    const PENDING     = 'pending';
    const AUTHORIZED  = 'authorized';
    const RECEIVED    = 'received';
    const INSPECTED   = 'inspected';
    const REFUNDED    = 'refunded';
    const REJECTED    = 'rejected';
    const CLOSED      = 'closed';

    public function toOptionArray()
    {
        return [
            ['value' => self::PENDING,    'label' => __('Pending')],
            ['value' => self::AUTHORIZED, 'label' => __('Authorized')],
            ['value' => self::RECEIVED,   'label' => __('Received')],
            ['value' => self::INSPECTED,  'label' => __('Inspected')],
            ['value' => self::REFUNDED,   'label' => __('Refunded')],
            ['value' => self::REJECTED,   'label' => __('Rejected')],
            ['value' => self::CLOSED,     'label' => __('Closed')],
        ];
    }

    public function getAllOptions()
    {
        return $this->toOptionArray();
    }
}