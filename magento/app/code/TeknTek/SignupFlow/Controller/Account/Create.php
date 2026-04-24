<?php

namespace TeknTek\SignupFlow\Controller\Account;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use TeknTek\SignupFlow\Model\SignupSession;

class Create extends Action implements HttpPostActionInterface
{
    private JsonFactory $resultJsonFactory;
    private AccountManagementInterface $accountManagement;
    private CustomerInterfaceFactory $customerFactory;
    private CustomerSession $customerSession;
    private SignupSession $signupSession;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        AccountManagementInterface $accountManagement,
        CustomerInterfaceFactory $customerFactory,
        CustomerSession $customerSession,
        SignupSession $signupSession,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->accountManagement = $accountManagement;
        $this->customerFactory = $customerFactory;
        $this->customerSession = $customerSession;
        $this->signupSession = $signupSession;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $email = strtolower(trim((string)$this->getRequest()->getParam('email')));
        $firstname = trim((string)$this->getRequest()->getParam('firstname'));
        $lastname = trim((string)$this->getRequest()->getParam('lastname'));
        $prefix = trim((string)$this->getRequest()->getParam('prefix'));
        $telephone = trim((string)$this->getRequest()->getParam('telephone'));
        $password = trim((string)$this->getRequest()->getParam('password'));
        $passwordConfirmation = trim((string)$this->getRequest()->getParam('password_confirmation'));

        if (!$this->signupSession->hasVerifiedEmail() || $this->signupSession->getEmail() === '') {
            return $result->setData([
                'success' => false,
                'message' => __('Please verify your email address first.'),
            ]);
        }

        if ($email === '' || strcasecmp($email, $this->signupSession->getEmail()) !== 0) {
            return $result->setData([
                'success' => false,
                'message' => __('Please use the verified email address to continue.'),
            ]);
        }

        if ($firstname === '' || $lastname === '') {
            return $result->setData([
                'success' => false,
                'message' => __('Please fill in your name details.'),
            ]);
        }

        if ($password !== $passwordConfirmation) {
            return $result->setData([
                'success' => false,
                'message' => __('New Password and Confirm New Password values did not match.'),
            ]);
        }

        if (!$this->isPasswordStrongEnough($password)) {
            return $result->setData([
                'success' => false,
                'message' => __('Password must be at least 8 characters, start with an uppercase letter, and contain at least one number.'),
            ]);
        }

        try {
            $store = $this->storeManager->getStore();
            $customer = $this->customerFactory->create();
            $customer->setEmail($email);
            $customer->setFirstname($firstname);
            $customer->setLastname($lastname);
            $customer->setStoreId((int)$store->getId());
            $customer->setWebsiteId((int)$store->getWebsiteId());

            if ($prefix !== '') {
                $customer->setPrefix($prefix);
            }

            $createdCustomer = $this->accountManagement->createAccount($customer, $password);
            $this->signupSession->clear();

            $redirectUrl = $this->_url->getUrl('customer/account');
            $message = __('Account created successfully.');

            if ($this->accountManagement->getConfirmationStatus((int)$createdCustomer->getId()) !== AccountManagementInterface::ACCOUNT_CONFIRMATION_NOT_REQUIRED) {
                $redirectUrl = $this->_url->getUrl('customer/account/login');
                $message = __('Your account has been created. Please check your email to activate it.');
            } else {
                $this->customerSession->setCustomerDataAsLoggedIn($createdCustomer);
            }

            return $result->setData([
                'success' => true,
                'message' => $message,
                'redirect_url' => $redirectUrl,
                'telephone' => $telephone,
                'email' => $email,
            ]);
        } catch (LocalizedException $exception) {
            $message = (string)$exception->getMessage();

            if (
                stripos($message, 'already exists') !== false
                || stripos($message, 'same email address') !== false
            ) {
                $redirectUrl = $this->_url->getUrl('customer/account/login');
                $friendlyMessage = __('An account with this email already exists. Please log in instead.');

                $this->customerSession->setData('tekntek_login_error', (string)$friendlyMessage);
                $this->signupSession->clear();

                return $result->setData([
                    'success' => false,
                    'message' => $friendlyMessage,
                    'redirect_to_login' => true,
                    'redirect_url' => $redirectUrl,
                ]);
            }

            return $result->setData([
                'success' => false,
                'message' => $message,
            ]);
        } catch (\Throwable $throwable) {
            return $result->setData([
                'success' => false,
                'message' => __('Unable to create your account right now. Please try again.'),
            ]);
        }
    }

    private function isPasswordStrongEnough(string $password): bool
    {
        return strlen($password) >= 8 && preg_match('/^[A-Z]/', $password) === 1 && preg_match('/\d/', $password) === 1;
    }
}
