<?php
declare(strict_types=1);

namespace TeknTek\SearchSuggestion\Controller\Review;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;

class Submit extends Action
{
    private JsonFactory $resultJsonFactory;
    private FormKeyValidator $formKeyValidator;
    private CustomerSession $customerSession;
    private ProductRepositoryInterface $productRepository;
    private ReviewFactory $reviewFactory;
    private RatingFactory $ratingFactory;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        FormKeyValidator $formKeyValidator,
        CustomerSession $customerSession,
        ProductRepositoryInterface $productRepository,
        ReviewFactory $reviewFactory,
        RatingFactory $ratingFactory,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->customerSession = $customerSession;
        $this->productRepository = $productRepository;
        $this->reviewFactory = $reviewFactory;
        $this->ratingFactory = $ratingFactory;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $request = $this->getRequest();

        if (!$request instanceof HttpRequest || !$request->isPost() || !$this->formKeyValidator->validate($request)) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __('Invalid review request. Please refresh the page and try again.'),
            ]);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(401)->setData([
                'success' => false,
                'login_url' => $this->_url->getUrl('customer/account/login'),
                'message' => (string) __('Please sign in to submit a review.'),
            ]);
        }

        $productId = (int) $request->getParam('product_id');
        $stars = max(1, min(5, (int) $request->getParam('stars', 5)));
        $postedRatings = (array) $request->getParam('ratings', []);
        $title = trim((string) $request->getParam('title', ''));
        $detail = trim((string) $request->getParam('detail', ''));

        if ($productId <= 0 || $detail === '') {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __('Please enter a review before submitting.'),
            ]);
        }

        if ($title === '') {
            $title = (string) __('New review');
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $product = $this->productRepository->getById($productId, false, $storeId);
            if (!$product->isVisibleInCatalog() || !$product->isVisibleInSiteVisibility()) {
                throw new LocalizedException(__('This product cannot be reviewed.'));
            }

            $customer = $this->customerSession->getCustomer();
            $nickname = trim((string) $customer->getFirstname() . ' ' . (string) $customer->getLastname());
            if ($nickname === '') {
                $nickname = (string) $customer->getEmail();
            }
            if ($nickname === '') {
                $nickname = (string) __('Customer');
            }

            $review = $this->reviewFactory->create();
            $review->setData([
                'nickname' => $nickname,
                'title' => $title,
                'detail' => $detail,
            ]);

            $validate = $review->validate();
            if ($validate !== true) {
                return $result->setHttpResponseCode(400)->setData([
                    'success' => false,
                    'message' => is_array($validate) ? implode(' ', $validate) : (string) __('We can\'t post your review right now.'),
                ]);
            }

            $review->setEntityId($review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE))
                ->setEntityPkValue($productId)
                ->setStatusId(Review::STATUS_PENDING)
                ->setCustomerId((int) $this->customerSession->getCustomerId())
                ->setStoreId($storeId)
                ->setStores([$storeId])
                ->save();

            $ratings = $this->ratingFactory->create()->getResourceCollection()
                ->addEntityFilter('product')
                ->setPositionOrder()
                ->addRatingPerStoreName($storeId)
                ->setStoreFilter($storeId)
                ->setActiveFilter(true)
                ->load()
                ->addOptionToItems();

            if (!$ratings->getSize()) {
                $ratings = $this->ratingFactory->create()->getResourceCollection()
                    ->addEntityFilter('product')
                    ->setPositionOrder()
                    ->setActiveFilter(true)
                    ->load()
                    ->addOptionToItems();
            }

            foreach ($ratings as $rating) {
                $ratingId = (int) $rating->getId();
                $optionId = isset($postedRatings[$ratingId]) ? (int) $postedRatings[$ratingId] : 0;

                if ($optionId <= 0) {
                    foreach ($rating->getOptions() as $option) {
                        if ((int) $option->getValue() === $stars) {
                            $optionId = (int) $option->getId();
                            break;
                        }
                    }
                }

                if ($optionId > 0) {
                    $rating->setReviewId((int) $review->getId())
                        ->setCustomerId((int) $this->customerSession->getCustomerId())
                        ->addOptionVote($optionId, $productId);
                }
            }

            $review->aggregate();

            return $result->setData([
                'success' => true,
                'review' => [
                    'id' => (int) $review->getId(),
                    'stars' => $stars,
                    'date' => date('d/m/Y'),
                    'user' => 'You',
                    'title' => $title,
                    'content' => $detail,
                    'pros' => [],
                    'cons' => [],
                    'up' => 0,
                    'down' => 0,
                    'user_vote' => '',
                    'can_delete' => true,
                ],
                'message' => (string) __('You submitted your review for moderation.'),
            ]);
        } catch (\Throwable $exception) {
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string) __('We can\'t post your review right now.'),
            ]);
        }
    }
}
