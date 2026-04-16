<?php
namespace Vendor\Rma\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Rma extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_rma_request', 'entity_id');
    }
}