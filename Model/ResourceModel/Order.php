<?php
namespace TimeExpressParcels\TimeExpressParcels\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Order extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('timeexpressparcels_order', 'entity_id');
    }
}
