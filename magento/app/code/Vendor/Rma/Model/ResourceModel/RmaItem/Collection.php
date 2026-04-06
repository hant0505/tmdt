<?php
namespace Vendor\Rma\Model\ResourceModel\RmaItem;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Vendor\Rma\Model\RmaItem::class,
            \Vendor\Rma\Model\ResourceModel\RmaItem::class
        );
    }
}