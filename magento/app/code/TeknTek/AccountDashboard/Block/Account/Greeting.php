<?php

declare(strict_types=1);

namespace TeknTek\AccountDashboard\Block\Account;

use Magento\Customer\Helper\View as CustomerViewHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Greeting extends Template
{
    private CustomerSession $customerSession;
    private CustomerViewHelper $customerViewHelper;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        CustomerViewHelper $customerViewHelper,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->customerViewHelper = $customerViewHelper;
        parent::__construct($context, $data);
    }

    public function getCustomerName(): string
    {
        try {
            if (!$this->customerSession->isLoggedIn()) {
                return '';
            }

            $customer = $this->customerSession->getCustomerData();
            if (!$customer) {
                return '';
            }

            $name = trim((string) $this->customerViewHelper->getCustomerName($customer));
            if ($name !== '') {
                return $name;
            }

            return trim((string) $customer->getFirstname() . ' ' . (string) $customer->getLastname());
        } catch (\Throwable $e) {
            return '';
        }
    }
}
