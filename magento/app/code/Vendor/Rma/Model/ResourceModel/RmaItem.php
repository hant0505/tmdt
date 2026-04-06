<?php
namespace Vendor\Rma\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class RmaItem extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_rma_item', 'item_id');
    }
}