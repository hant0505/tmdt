<?php

declare(strict_types=1);

namespace TeknTek\FooterDocs\Controller\Page;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    private const TITLES = [
        'stores' => 'Stores',
        'corporate' => 'Corporate Website',
        'offers' => 'Exclusive Offers',
        'career' => 'Career',
        'help-center' => 'Help Center',
        'payments' => 'Payments',
        'returns' => 'Product Returns',
        'faq' => 'FAQ',
    ];

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $topic = (string) $this->getRequest()->getParam('topic', 'help-center');
        $title = self::TITLES[$topic] ?? self::TITLES['help-center'];

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__($title));

        return $resultPage;
    }
}
