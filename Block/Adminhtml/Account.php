<?php
 
namespace TimeExpressParcels\TimeExpressParcels\Block\Adminhtml;
 
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;

class Account extends Template
{
    protected $formKey;
    public function __construct(Context $context, FormKey $formKey, array $data = [])
    {
        $this->formKey = $formKey;
        parent::__construct($context, $data);
    }
    public function getAccount()
    {
        $helper = $this->getHelper();
        $account = $helper->getStoreConfigDb('timeexpressparcels_account_info');
        if (!$account) {
            return false;
        } else {
            return $account;
        }
    }
    
    public function getHelper()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->create('TimeExpressParcels\TimeExpressParcels\Helper\Data');
        return $helper;
    }
    
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }
}
