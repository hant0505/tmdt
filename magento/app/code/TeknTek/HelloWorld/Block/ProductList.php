<?php
namespace TeknTek\HelloWorld\Block;

use Magento\Framework\View\Element\Template;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class ProductList extends Template
{
    protected $collectionFactory;

    public function __construct(
        Template\Context $context,
        CollectionFactory $collectionFactory,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $data);
    }

    public function getProducts()
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect('*')->setPageSize(5);
        return $collection;
    }
}
