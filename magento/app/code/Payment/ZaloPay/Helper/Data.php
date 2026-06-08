<?php

declare(strict_types=1);

namespace Payment\ZaloPay\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{
    private const CONFIG_PATH = 'payment/zalopay/';
    private const DEFAULT_SANDBOX_ENDPOINT = 'https://sb-openapi.zalopay.vn/v2/create';

    private EncryptorInterface $encryptor;

    private Curl $curl;

    private Json $json;

    private TimezoneInterface $timezone;

    private UrlInterface $urlBuilder;

    private OrderRepositoryInterface $orderRepository;

    private OrderCollectionFactory $orderCollectionFactory;

    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        Curl $curl,
        Json $json,
        TimezoneInterface $timezone,
        UrlInterface $urlBuilder,
        OrderRepositoryInterface $orderRepository,
        OrderCollectionFactory $orderCollectionFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->curl = $curl;
        $this->json = $json;
        $this->timezone = $timezone;
        $this->urlBuilder = $urlBuilder;
        $this->orderRepository = $orderRepository;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
    }

    public function getConfigValue(string $field, ?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::CONFIG_PATH . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value !== null ? (string)$value : null;
    }

    public function getSecretValue(string $field, ?int $storeId = null): string
    {
        $value = trim((string)$this->getConfigValue($field, $storeId));
        if ($value === '') {
            return '';
        }

        // Defaults in config.xml are plain public sandbox keys; Admin-saved values are encrypted.
        if (strpos($value, ':') === false) {
            return $value;
        }

        $decrypted = (string)$this->encryptor->decrypt($value);

        return $decrypted !== '' ? $decrypted : $value;
    }

    public function isDebug(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH . 'debug',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function createZaloPayOrder(Order $order): array
    {
        $storeId = (int)$order->getStoreId();
        $key1 = $this->getSecretValue('key1', $storeId);
        if ($key1 === '') {
            throw new LocalizedException(__('ZaloPay Sandbox Key 1 is missing.'));
        }

        $appTransId = $this->buildAppTransId((string)$order->getIncrementId());
        $amount = (int)round((float)$order->getGrandTotal());
        if ($amount <= 0) {
            throw new LocalizedException(__('ZaloPay amount is invalid.'));
        }

        $embedData = [
            'redirecturl' => $this->urlBuilder->getUrl('zalopay/payment/returnurl', ['_secure' => true]),
            'order_increment_id' => (string)$order->getIncrementId()
        ];
        $item = $this->buildItemData($order);
        $embedDataJson = $this->json->serialize($embedData);
        $itemJson = $this->json->serialize($item);
        $appTime = (int)round(microtime(true) * 1000);
        $appUser = $this->getAppUser($order);

        $params = [
            'app_id' => (int)$this->getConfigValue('app_id', $storeId),
            'app_trans_id' => $appTransId,
            'app_user' => $appUser,
            'app_time' => $appTime,
            'amount' => $amount,
            'item' => $itemJson,
            'embed_data' => $embedDataJson,
            'description' => 'ZaloPay Sandbox payment for order #' . $order->getIncrementId(),
            'callback_url' => $this->urlBuilder->getUrl('zalopay/payment/callback', ['_secure' => true])
        ];

        $macInput = implode('|', [
            $params['app_id'],
            $params['app_trans_id'],
            $params['app_user'],
            $params['amount'],
            $params['app_time'],
            $params['embed_data'],
            $params['item']
        ]);
        $params['mac'] = hash_hmac('sha256', $macInput, $key1);

        $payment = $order->getPayment();
        $payment->setAdditionalInformation('app_trans_id', $appTransId);
        $payment->setAdditionalInformation('zalopay_amount', $amount);
        $this->orderRepository->save($order);

        $endpoint = trim((string)$this->getConfigValue('create_order_endpoint', $storeId));
        if ($endpoint === '') {
            $endpoint = self::DEFAULT_SANDBOX_ENDPOINT;
        }

        $this->curl->setHeaders(['Content-Type' => 'application/x-www-form-urlencoded']);
        $this->curl->post($endpoint, $params);

        $responseBody = (string)$this->curl->getBody();
        try {
            $response = $this->json->unserialize($responseBody);
        } catch (\InvalidArgumentException $exception) {
            $this->debug('Invalid create-order response', ['order_id' => $order->getEntityId()]);
            throw new LocalizedException(__('ZaloPay create order response is invalid.'));
        }

        if (!is_array($response) || empty($response['order_url'])) {
            $message = $response['return_message'] ?? $response['sub_return_message'] ?? 'ZaloPay did not return order_url.';
            throw new LocalizedException(__($message));
        }

        $payment->setAdditionalInformation('zalopay_order_token', $response['order_token'] ?? null);
        $order->addCommentToStatusHistory('ZaloPay Sandbox order created. app_trans_id: ' . $appTransId);
        $this->orderRepository->save($order);

        $this->debug('Create order success', [
            'order_id' => $order->getEntityId(),
            'app_trans_id' => $appTransId
        ]);

        return $response;
    }

    public function verifyCallbackMac(string $data, string $mac, ?int $storeId = null): bool
    {
        $key2 = $this->getSecretValue('key2', $storeId);
        if ($key2 === '') {
            return false;
        }

        $expectedMac = hash_hmac('sha256', $data, $key2);

        return hash_equals($expectedMac, $mac);
    }

    public function findOrderByAppTransId(string $appTransId): ?Order
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->getSelect()->join(
            ['payment' => $collection->getTable('sales_order_payment')],
            'main_table.entity_id = payment.parent_id',
            []
        )->where('payment.additional_information LIKE ?', '%' . $appTransId . '%');
        $collection->setPageSize(1);

        $order = $collection->getFirstItem();
        if ($order && $order->getId()) {
            return $order;
        }

        $incrementId = $this->extractIncrementId($appTransId);
        if ($incrementId === '') {
            return null;
        }

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('increment_id', $incrementId);
        $collection->setPageSize(1);
        $order = $collection->getFirstItem();

        return $order && $order->getId() ? $order : null;
    }

    public function markOrderPaid(Order $order, array $callbackData): void
    {
        if ($order->getState() === Order::STATE_PROCESSING || $order->getState() === Order::STATE_COMPLETE) {
            return;
        }

        $payment = $order->getPayment();
        if (!empty($callbackData['app_trans_id'])) {
            $payment->setAdditionalInformation('app_trans_id', (string)$callbackData['app_trans_id']);
        }
        if (!empty($callbackData['zp_trans_id'])) {
            $payment->setAdditionalInformation('zp_trans_id', (string)$callbackData['zp_trans_id']);
            $payment->setTransactionId((string)$callbackData['zp_trans_id']);
            $payment->setIsTransactionClosed(true);
        }

        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
        $order->addCommentToStatusHistory('Paid by ZaloPay Sandbox', false, true);
        $this->orderRepository->save($order);
    }

    public function validateCallbackOrder(Order $order, array $callbackData): void
    {
        $storeId = (int)$order->getStoreId();
        $configuredAppId = (int)$this->getConfigValue('app_id', $storeId);
        if (isset($callbackData['app_id']) && (int)$callbackData['app_id'] !== $configuredAppId) {
            throw new LocalizedException(__('ZaloPay callback app_id does not match.'));
        }

        if (isset($callbackData['amount'])) {
            $expectedAmount = (int)round((float)$order->getGrandTotal());
            if ((int)$callbackData['amount'] !== $expectedAmount) {
                throw new LocalizedException(__('ZaloPay callback amount does not match.'));
            }
        }
    }

    public function debug(string $message, array $context = []): void
    {
        if ($this->isDebug()) {
            $this->logger->debug('[ZaloPay Sandbox] ' . $message, $context);
        }
    }

    private function buildAppTransId(string $incrementId): string
    {
        return $this->timezone->date()->format('ymd') . '_' . preg_replace('/[^0-9A-Za-z]/', '', $incrementId);
    }

    private function extractIncrementId(string $appTransId): string
    {
        $parts = explode('_', $appTransId, 2);

        return $parts[1] ?? '';
    }

    private function buildItemData(Order $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'itemid' => (string)$item->getSku(),
                'itemname' => (string)$item->getName(),
                'itemprice' => (int)round((float)$item->getPriceInclTax()),
                'itemquantity' => (int)$item->getQtyOrdered()
            ];
        }

        return $items;
    }

    private function getAppUser(Order $order): string
    {
        if ($order->getCustomerId()) {
            return 'customer_' . $order->getCustomerId();
        }

        $email = (string)$order->getCustomerEmail();
        if ($email !== '') {
            return substr(preg_replace('/[^0-9A-Za-z._-]/', '_', $email), 0, 50);
        }

        return 'guest';
    }
}
