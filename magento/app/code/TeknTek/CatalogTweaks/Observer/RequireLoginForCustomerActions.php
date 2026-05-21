<?php

declare(strict_types=1);

namespace TeknTek\CatalogTweaks\Observer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;

class RequireLoginForCustomerActions implements ObserverInterface
{
    /**
     * @var string[]
     */
    private array $protectedActions = [
        'checkout_cart_add',
        'wishlist_index_add',
        'catalog_product_compare_add',
        'checkout_index_index',
    ];

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly ActionFlag $actionFlag,
        private readonly RedirectInterface $redirect,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($this->customerSession->isLoggedIn()) {
            return;
        }

        $controllerAction = $observer->getEvent()->getControllerAction();
        if (!$controllerAction) {
            return;
        }

        $request = $controllerAction->getRequest();
        $fullActionName = (string) $request->getFullActionName();

        if (!in_array($fullActionName, $this->protectedActions, true)) {
            return;
        }

        $this->customerSession->setBeforeAuthUrl($this->urlBuilder->getCurrentUrl());
        $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);
        $this->redirect->redirect($controllerAction->getResponse(), 'customer/account/login');
    }
}
