<?php
namespace TimeExpressParcels\TimeExpressParcels\Block\Adminhtml\Sales\Order;

use TimeExpressParcels\TimeExpressParcels\Model\OrderFactory as TimeExpressParcelsOrder;
use TimeExpressParcels\TimeExpressParcels\Model\ResourceModel\Order as TimeExpressParcelsOrderResource;

class View extends \Magento\Backend\Block\Template
{
    protected $helper;
    protected $request;
    protected $orderFactory;
    protected $orderResourceModel;
    protected $timeexpressparcelsOrder;
    protected $timeexpressparcelsOrderResource;

    public function __construct(
        \TimeExpressParcels\TimeExpressParcels\Helper\Data $helper,
        \Magento\Framework\App\RequestInterface            $request,
        \Magento\Sales\Model\OrderFactory                  $orderFactory,
        \Magento\Sales\Model\ResourceModel\Order           $orderResourceModel,
        \Magento\Backend\Block\Template\Context            $context,
        TimeExpressParcelsOrder                            $timeexpressparcelsOrder,
        TimeExpressParcelsOrderResource                    $timeexpressparcelsOrderResource,
        array                                              $data = []
    ) {
        $this->helper = $helper;
        $this->request = $request;
        $this->orderFactory = $orderFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->timeexpressparcelsOrder = $timeexpressparcelsOrder;
        $this->timeexpressparcelsOrderResource = $timeexpressparcelsOrderResource;
        parent::__construct($context, $data);
    }

    public function getResponseValues()
    {
        $order_id = (int)$this->request->getParam('order_id');
        // Magento Order
        $order = $this->orderFactory->create();
        $this->orderResourceModel->load($order, $order_id);
        $isOrderUsedTimeExpressParcels = false;
        $shippingMethod = $order->getShippingMethod();
        $methods = $this->helper->getMethods();
        foreach ($methods as $serviceCode => $serviceName) {
            $code = 'timeexpressparcels_' . $serviceCode;
            if ($code == $shippingMethod) {
                $isOrderUsedTimeExpressParcels = true;
                break;
            }
        }

        if ($isOrderUsedTimeExpressParcels) {
            $quoteId = $order->getQuoteId();
            // Time Express Parcels Order model
            $model = $this->timeexpressparcelsOrder->create();
            $this->timeexpressparcelsOrderResource->load($model, $quoteId, 'quote_id');
            if ($model && $model->getId() > 0) {
                $this->timeexpressparcelsOrderResource->load($model, $model->getId());
                $awbNo =  $model->getAwbno();
                $html = $awbNo;
                if ($awbNo) {
                    $trackUrl = 'https://www.timexpress.ae/track/' . $awbNo;
                    $html = $awbNo . ' : <a target="blank" href="' . $trackUrl . '">Track Here</a>';
                } else {
                    $createUrl = $this->getUrl('timeexpressparcels/order/track', [
                        'order_id'=>$quoteId,
                        'from' => 'order_view',
                        'order' => $order_id
                    ]);
                    $html ='<a href="' . $createUrl . '">Create Tracking Number</a>';
                }
                return $html;
            }
        }
        return false;
    }
}
