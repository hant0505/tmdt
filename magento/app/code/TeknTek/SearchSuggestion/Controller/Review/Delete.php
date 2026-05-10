<?php
declare(strict_types=1);

namespace TeknTek\SearchSuggestion\Controller\Review;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Review\Model\ReviewFactory;

class Delete extends Action implements CsrfAwareActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CustomerSession $customerSession,
        private readonly ResourceConnection $resource,
        private readonly ReviewFactory $reviewFactory
    ) {
        parent::__construct($context);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $request = $this->getRequest();

        if (!$request instanceof HttpRequest || !$request->isPost()) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __('Invalid delete request. Please refresh the page and try again.'),
            ]);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(401)->setData([
                'success' => false,
                'login_url' => $this->_url->getUrl('customer/account/login'),
                'message' => (string) __('Please sign in to delete your review.'),
            ]);
        }

        $reviewId = (int) $request->getParam('review_id');
        $customerId = (int) $this->customerSession->getCustomerId();

        if ($reviewId <= 0) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __('Invalid review.'),
            ]);
        }

        $connection = $this->resource->getConnection();
        $detailTable = $this->resource->getTableName('review_detail');
        $ownerId = (int) $connection->fetchOne(
            $connection->select()
                ->from($detailTable, 'customer_id')
                ->where('review_id = ?', $reviewId)
                ->limit(1)
        );

        if ($ownerId !== $customerId) {
            return $result->setHttpResponseCode(403)->setData([
                'success' => false,
                'message' => (string) __('You can only delete reviews you wrote.'),
            ]);
        }

        try {
            $review = $this->reviewFactory->create()->load($reviewId);
            if (!$review->getId()) {
                return $result->setHttpResponseCode(404)->setData([
                    'success' => false,
                    'message' => (string) __('This review no longer exists.'),
                ]);
            }

            $entityPkValue = (int) $review->getEntityPkValue();
            $entityId = (int) $review->getEntityId();
            $helpfulnessTable = $this->resource->getTableName('tekntek_review_helpfulness');
            if ($connection->isTableExists($helpfulnessTable)) {
                $connection->delete($helpfulnessTable, ['review_id = ?' => $reviewId]);
            }

            $connection->beginTransaction();
            try {
                $connection->delete($this->resource->getTableName('rating_option_vote'), ['review_id = ?' => $reviewId]);
                $connection->delete($this->resource->getTableName('review_store'), ['review_id = ?' => $reviewId]);
                $connection->delete($detailTable, ['review_id = ?' => $reviewId]);
                $connection->delete($this->resource->getTableName('review'), ['review_id = ?' => $reviewId]);
                $connection->commit();
            } catch (\Throwable $exception) {
                $connection->rollBack();
                throw $exception;
            }

            $review->setEntityPkValue($entityPkValue);
            $review->setEntityId($entityId);
            $review->aggregate();

            return $result->setData([
                'success' => true,
                'review_id' => $reviewId,
            ]);
        } catch (\Throwable $exception) {
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string) __('We can\'t delete this review right now.'),
            ]);
        }
    }
}
