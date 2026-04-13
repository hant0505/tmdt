<?php

namespace Vendor\Weather\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class Index extends Action
{
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function execute()
    {
<<<<<<< HEAD:magento/app/code/TeknTek/HelloWorld/Controller/Index/Index.php
        // Trả về Page object để Magento tự load Layout XML
        // Nó sẽ tự tìm file layout helloworld_index_index.xml
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
=======
        return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
>>>>>>> origin/dev:magento/app/code/Vendor/Weather/Controller/Index/Index.php
    }
}