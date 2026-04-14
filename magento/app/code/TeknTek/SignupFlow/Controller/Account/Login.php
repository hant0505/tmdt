<?php

declare(strict_types=1);

namespace TeknTek\SignupFlow\Controller\Account;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class Login extends Action
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerFactory $customerFactory,
        private readonly CustomerSession $customerSession
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        try {
            if (!$this->getRequest()->isPost()) {
                throw new LocalizedException(__('Invalid request.'));
            }

            if (!$this->formKeyValidator->validate($this->getRequest())) {
                throw new LocalizedException(__('Your session has expired. Please refresh and try again.'));
            }

            $login = (array)$this->getRequest()->getParam('login', []);
            $email = trim((string)($login['username'] ?? ''));
            $password = (string)($login['password'] ?? '');

            if ($email === '' || $password === '') {
                throw new LocalizedException(__('Please enter your email and password.'));
            }

            $websiteId = (int)$this->storeManager->getWebsite()->getId();
            try {
                $this->customerRepository->get($email, $websiteId);
            } catch (NoSuchEntityException $exception) {
                throw new LocalizedException(__('This account does not exist.'));
            }

            // Authenticate credentials first; this throws on wrong password.
            $this->accountManagement->authenticate($email, $password);

            $customerModel = $this->customerFactory->create();
            $customerModel->setWebsiteId($websiteId);
            $customerModel->loadByEmail($email);
            if (!$customerModel->getId()) {
                throw new LocalizedException(__('Unable to log in right now. Please try again.'));
            }

            $this->customerSession->setCustomerAsLoggedIn($customerModel);
            $this->customerSession->regenerateId();

            return $result->setData([
                'success' => true,
                'redirect_url' => $this->_url->getUrl('customer/account')
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage()
            ]);
        } catch (\Throwable $exception) {
            return $result->setData([
                'success' => false,
                'message' => __('The account sign-in was incorrect or your account is disabled temporarily. Please wait and try again later.')
            ]);
        }
    }
}
