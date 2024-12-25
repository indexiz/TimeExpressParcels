<?php

namespace TimeExpressParcels\TimeExpressParcels\Model;
 
use Magento\Framework\Data\OptionSourceInterface;
use TimeExpressParcels\TimeExpressParcels\Helper\Data as TimeExpressParcelsHelper;
 
class ServiceType implements OptionSourceInterface
{
    protected $helper;
    
    public function __construct(
        TimeExpressParcelsHelper $helper
    ) {
        $this->helper = $helper;
    }
    
    public function getOptionArray()
    {
        $options = [];

        $methods = $this->helper->getMethods();
        foreach ($methods as $serviceCode => $serviceName) {
            $options[$serviceCode] = $serviceName;
        }
        return $options;
    }
 
    public function getAllOptions()
    {
        $res = $this->getOptions();
        array_unshift($res, ['value' => '', 'label' => '']);
        return $res;
    }
 
    public function getOptions()
    {
        $res = [];
        foreach ($this->getOptionArray() as $index => $value) {
            $res[] = ['value' => $index, 'label' => $value];
        }
        return $res;
    }
 
    public function toOptionArray()
    {
        return $this->getOptions();
    }
}
