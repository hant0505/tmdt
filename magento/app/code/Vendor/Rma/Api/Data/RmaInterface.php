<?php
namespace Vendor\Rma\Api\Data;

interface RmaInterface
{
    const RMA_ID          = 'rma_id';
    const ORDER_ID        = 'order_id';
    const RMA_NUMBER      = 'rma_number';
    const CUSTOMER_ID     = 'customer_id';
    const CUSTOMER_EMAIL  = 'customer_email';
    const STATUS          = 'status';
    const REASON          = 'reason';
    const RESOLUTION      = 'resolution';
    const CUSTOMER_NOTES  = 'customer_notes';
    const CREATED_AT      = 'created_at';
    const UPDATED_AT      = 'updated_at';

    /**
     * @return int
     */
    public function getRmaId();

    /**
     * @param int $rmaId
     * @return $this
     */
    public function setRmaId($rmaId);

    /**
     * @return int
     */
    public function getOrderId();

    /**
     * @param int $orderId
     * @return $this
     */
    public function setOrderId($orderId);

    /**
     * @return string
     */
    public function getRmaNumber();

    /**
     * @param string $rmaNumber
     * @return $this
     */
    public function setRmaNumber($rmaNumber);

    /**
     * @return int|null
     */
    public function getCustomerId();

    /**
     * @param int|null $customerId
     * @return $this
     */
    public function setCustomerId($customerId);

    /**
     * @return string
     */
    public function getCustomerEmail();

    /**
     * @param string $customerEmail
     * @return $this
     */
    public function setCustomerEmail($customerEmail);

    /**
     * @return string
     */
    public function getStatus();

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * @return string|null
     */
    public function getReason();

    /**
     * @param string|null $reason
     * @return $this
     */
    public function setReason($reason);

    /**
     * @return string|null
     */
    public function getResolution();

    /**
     * @param string|null $resolution
     * @return $this
     */
    public function setResolution($resolution);

    /**
     * @return string|null
     */
    public function getCustomerNotes();

    /**
     * @param string|null $customerNotes
     * @return $this
     */
    public function setCustomerNotes($customerNotes);

    /**
     * @return string
     */
    public function getCreatedAt();

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * @return string
     */
    public function getUpdatedAt();

    /**
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt);
}