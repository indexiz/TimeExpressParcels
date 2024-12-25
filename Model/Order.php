<?php

namespace TimeExpressParcels\TimeExpressParcels\Model;

use Magento\Framework\Model\AbstractModel;

class Order extends AbstractModel
{
    protected function _construct()
    {
        $this->_init('TimeExpressParcels\TimeExpressParcels\Model\ResourceModel\Order');
    }
}
