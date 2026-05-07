<?php
namespace Vendor\Rma\Model\ResourceModel\Rma;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Vendor\Rma\Model\Rma::class,
            \Vendor\Rma\Model\ResourceModel\Rma::class
        );
    }
}