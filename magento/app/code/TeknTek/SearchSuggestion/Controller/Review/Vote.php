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

class Vote extends Action implements CsrfAwareActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CustomerSession $customerSession,
        private readonly ResourceConnection $resource
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
                'message' => (string) __('Invalid vote request. Please refresh the page and try again.'),
            ]);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(401)->setData([
                'success' => false,
                'login_url' => $this->_url->getUrl('customer/account/login'),
                'message' => (string) __('Please sign in to vote on reviews.'),
            ]);
        }

        $reviewId = (int) $request->getParam('review_id');
        $type = (string) $request->getParam('type');
        $voteType = $type === 'down' ? -1 : 1;
        $customerId = (int) $this->customerSession->getCustomerId();

        if ($reviewId <= 0) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __('Invalid review.'),
            ]);
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('tekntek_review_helpfulness');
        $reviewTable = $this->resource->getTableName('review');

        if (!in_array($type, ['up', 'down'], true)) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __('Invalid vote type.'),
            ]);
        }

        $reviewExists = (bool) $connection->fetchOne(
            $connection->select()
                ->from($reviewTable, 'review_id')
                ->where('review_id = ?', $reviewId)
                ->limit(1)
        );

        if (!$reviewExists) {
            return $result->setHttpResponseCode(404)->setData([
                'success' => false,
                'message' => (string) __('This review no longer exists.'),
            ]);
        }

        try {
            $connection->insertOnDuplicate(
                $table,
                [
                    'review_id' => $reviewId,
                    'customer_id' => $customerId,
                    'vote_type' => $voteType,
                ],
                ['vote_type']
            );
        } catch (\Throwable $exception) {
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string) __('We can\'t save your vote right now.'),
            ]);
        }

        return $result->setData([
            'success' => true,
            'review_id' => $reviewId,
            'user_vote' => $voteType === 1 ? 'up' : 'down',
            'up' => (int) $connection->fetchOne(
                $connection->select()->from($table, new \Zend_Db_Expr('COUNT(*)'))
                    ->where('review_id = ?', $reviewId)
                    ->where('vote_type = ?', 1)
            ),
            'down' => (int) $connection->fetchOne(
                $connection->select()->from($table, new \Zend_Db_Expr('COUNT(*)'))
                    ->where('review_id = ?', $reviewId)
                    ->where('vote_type = ?', -1)
            ),
        ]);
    }
}
