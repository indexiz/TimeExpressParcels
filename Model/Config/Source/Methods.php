<?php
namespace TimeExpressParcels\TimeExpressParcels\Model\Config\Source;

use TimeExpressParcels\TimeExpressParcels\Helper\Data as TimeExpressParcelsHelper;

class Methods implements \Magento\Framework\Data\OptionSourceInterface
{
    protected $helper;

    public function __construct(TimeExpressParcelsHelper $helper)
    {
        $this->helper = $helper;
    }

    public function toOptionArray()
    {
        $methods = $this->helper->getMethods();
        $options = [];
        foreach ($methods as $code => $name) {
            $options[] = ['value' => $code, 'label' => $name];
        }
        return $options;
    }
}
