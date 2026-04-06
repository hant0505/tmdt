<?php
namespace Vendor\Rma\Model;

use Magento\Framework\Model\AbstractModel;

class RmaItem extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Vendor\Rma\Model\ResourceModel\RmaItem::class);
    }
}