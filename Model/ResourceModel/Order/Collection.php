<?php

namespace TimeExpressParcels\TimeExpressParcels\Model\ResourceModel\Order;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'TimeExpressParcels\TimeExpressParcels\Model\Order',
            'TimeExpressParcels\TimeExpressParcels\Model\ResourceModel\Order'
        );
    }
}
