<?php

namespace TeknTek\HelloWorld\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

class Index extends Action
{
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }


    public function execute()
    {
        // Trả về Page object để Magento tự load Layout XML
        // Nó sẽ tự tìm file layout helloworld_index_index.xml
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
    }

}
