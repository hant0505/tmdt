<?php

declare(strict_types=1);

namespace TeknTek\CatalogTweaks\Observer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
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
        // 'checkout_cart_add',
        // 'review_product_post',
        // 'tekntek_suggest_review_submit',
        // 'wishlist_index_add',
        'wishlist_index_add',
        'catalog_product_compare_add',
    ];

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly ActionFlag $actionFlag,
        private readonly RedirectInterface $redirect,
        private readonly UrlInterface $urlBuilder,
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($this->customerSession->isLoggedIn()) {
            return;
        }

        $fullActionName = (string) $this->request->getFullActionName();

        if (!in_array($fullActionName, $this->protectedActions, true)) {
            return;
        }

        $this->customerSession->setBeforeAuthUrl(
            $this->resolveBeforeAuthUrl($this->request)
        );

        $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);

        $this->redirect->redirect(
            $this->response,
            'customer/account/login'
        );
    }

    private function resolveBeforeAuthUrl(RequestInterface $request): string
    {
        $refererUrl = (string) $this->redirect->getRefererUrl();

        if ($refererUrl !== '') {
            return $refererUrl;
        }

        $uenc = (string) $request->getParam(Action::PARAM_NAME_URL_ENCODED, '');

        if ($uenc !== '') {
            $decoded = base64_decode(strtr($uenc, '-_,', '+/='), true);

            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $this->urlBuilder->getCurrentUrl();
    }
}