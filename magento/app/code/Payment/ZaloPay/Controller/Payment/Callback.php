<?php

declare(strict_types=1);

namespace Payment\ZaloPay\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Payment\ZaloPay\Helper\Data as ZaloPayHelper;

class Callback extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private JsonFactory $resultJsonFactory;

    private \Magento\Framework\Serialize\Serializer\Json $json;

    private ZaloPayHelper $zaloPayHelper;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \Magento\Framework\Serialize\Serializer\Json $json,
        ZaloPayHelper $zaloPayHelper
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->json = $json;
        $this->zaloPayHelper = $zaloPayHelper;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute(): Json
    {
        try {
            $payload = $this->json->unserialize((string)$this->getRequest()->getContent());
            if (!is_array($payload) || empty($payload['data']) || empty($payload['mac'])) {
                throw new LocalizedException(__('Invalid callback payload.'));
            }

            $data = (string)$payload['data'];
            $mac = (string)$payload['mac'];
            if (!$this->zaloPayHelper->verifyCallbackMac($data, $mac)) {
                throw new LocalizedException(__('Invalid callback MAC.'));
            }

            $callbackData = $this->json->unserialize($data);
            if (!is_array($callbackData) || empty($callbackData['app_trans_id'])) {
                throw new LocalizedException(__('Invalid callback data.'));
            }

            $order = $this->zaloPayHelper->findOrderByAppTransId((string)$callbackData['app_trans_id']);
            if (!$order) {
                throw new LocalizedException(__('Order not found.'));
            }

            $this->zaloPayHelper->validateCallbackOrder($order, $callbackData);
            $this->zaloPayHelper->markOrderPaid($order, $callbackData);

            return $this->successResponse();
        } catch (LocalizedException $exception) {
            return $this->failResponse($exception->getMessage());
        } catch (\Throwable $exception) {
            return $this->failResponse('Callback processing failed.');
        }
    }

    private function successResponse(): Json
    {
        return $this->resultJsonFactory->create()->setData([
            'return_code' => 1,
            'return_message' => 'success'
        ]);
    }

    private function failResponse(string $message): Json
    {
        return $this->resultJsonFactory->create()->setData([
            'return_code' => 2,
            'return_message' => $message
        ]);
    }
}
