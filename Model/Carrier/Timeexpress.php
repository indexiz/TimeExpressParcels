<?php
/**
 * Copyright © TIME EXPRESS PARCELS. All rights reserved.
 * See LICENSE.txt for license details (http://opensource.org/licenses/osl-3.0.php).
 */

namespace TimeExpressParcels\TimeExpressParcels\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use TimeExpressParcels\TimeExpressParcels\Helper\Data as TimeExpressParcelsHelper;
use TimeExpressParcels\TimeExpressParcels\Model\Api as TimeExpressParcelsApi;

class Timeexpress extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'timeexpressparcels';

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $rateMethodFactory;

    /**
     * @var \Magento\Shipping\Model\Tracking\ResultFactory
     */
    protected $trackFactory;

    /**
     * @var \Magento\Shipping\Model\Tracking\Result\StatusFactory
     */
    protected $trackStatusFactory;
    
    protected $helper;
    
    protected $api;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
     * @param \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        TimeExpressParcelsHelper $helper,
        TimeExpressParcelsApi $api,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->trackFactory = $trackFactory;
        $this->trackStatusFactory = $trackStatusFactory;
        $this->helper = $helper;
        $this->api = $api;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @param int|float $shippingPrice
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method
     */
    protected function createResultMethod($shippingPrice, $serviceCode, $serviceName)
    {
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier('timeexpressparcels');
        $method->setCarrierTitle('Time Express Parcels');

        $method->setMethod($serviceCode);
        $method->setMethodTitle($serviceName);

        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);
        return $method;
    }

    /**
     * @param RateRequest $request
     * @return Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }
        
        /** @var Result $result */
        $result = $this->rateResultFactory->create();
        
        $selectedMethods = $this->helper->getStoreConfig('carriers/timeexpressparcels/methods');

        $allowedMethods = [];
        if ($selectedMethods) {
            $allowedMethods = explode(',', $selectedMethods);
        }
     
        $methods = $this->helper->getMethods();
        foreach ($methods as $serviceCode => $serviceName) {
            if (in_array($serviceCode, $allowedMethods)) {
                if ($this->helper->canDisplay($serviceCode, $request)) {
                    $shippingPrice = $this->api->getShippingPrice($request, $serviceCode);

                    if ($shippingPrice !== false) {
                        $method = $this->createResultMethod($shippingPrice, $serviceCode, $serviceName);
                        $result->append($method);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['timeexpressparcels' => __('Time Express Parcels')];
    }
}
