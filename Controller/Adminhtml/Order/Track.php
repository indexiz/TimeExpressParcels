<?php

namespace TimeExpressParcels\TimeExpressParcels\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\Validator\Exception as MagentoException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use TimeExpressParcels\TimeExpressParcels\Helper\Data as TimeExpressParcelsHelper;
use TimeExpressParcels\TimeExpressParcels\Model\Api as TimeExpressParcelsApi;
use TimeExpressParcels\TimeExpressParcels\Model\CreateShipment;
use TimeExpressParcels\TimeExpressParcels\Model\OrderFactory as TimeExpressParcelsOrder;

class Track extends Action
{
    protected $_coreRegistry;

    protected $_resultPageFactory;

    protected $reportFactory;

    protected $helper;

    protected $api;

    protected $orderFactory;

    protected $quoteFactory;

    /**
     * @var QuoteResourceModel
     */
    protected $quoteResourceModel;

    protected $timeexpressparcelsOrder;

    /**
     * @var ShipmentRepositoryInterface
     */
    private $shipmentRepository;

    /**
     * @var CreateShipment
     */
    private $createShipment;

    public function __construct(
        Context $context,
        Registry $coreRegistry,
        PageFactory $resultPageFactory,
        TimeExpressParcelsHelper $helper,
        TimeExpressParcelsApi $api,
        OrderFactory $orderFactory,
        OrderResourceModel $orderResourceModel,
        QuoteFactory $quoteFactory,
        QuoteResourceModel $quoteResourceModel,
        TimeExpressParcelsOrder $timeexpressparcelsOrder,
        ShipmentRepositoryInterface $shipmentRepository,
        CreateShipment $createShipment
    ) {
        parent::__construct($context);
        $this->_coreRegistry = $coreRegistry;
        $this->_resultPageFactory = $resultPageFactory;
        $this->helper = $helper;
        $this->api = $api;
        $this->orderFactory = $orderFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->timeexpressparcelsOrder = $timeexpressparcelsOrder;
        $this->shipmentRepository = $shipmentRepository;
        $this->createShipment = $createShipment;
    }

    public function execute()
    {
        try {
            $quoteId = (int)strip_tags(trim($this->getRequest()->getParam('order_id') ?? ''));
            $shipmentId = (int)strip_tags(trim($this->getRequest()->getParam('shipment_id') ?? ''));

            if ($quoteId) {
                // Magento Order
                $order = $this->orderFactory->create();
                $this->orderResourceModel->load($order, $quoteId, 'quote_id');
                // Magento Order Shipment
                if (empty($shipmentId) || $shipmentId == 0) {
                    $shipmentId = $this->createShipment->createOrderShipment($order->getId());
                }
                // Magento Quote
                $quote = $this->quoteFactory->create();
                $this->quoteResourceModel->load($quote, $quoteId);
                if ($order && $order->getId() > 0) {
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
                        $timeExpressOrder = $this->timeexpressparcelsOrder->create()->load($quoteId, 'quote_id');
                        if (!empty($timeExpressOrder->getData('awbno'))) {
                            $errorMessage = __('Time Express Parcels Tracking for the order# %1 has already been created.', $timeExpressOrder->getData('increment_id'));
                            $this->messageManager->addErrorMessage($errorMessage);
                            $this->_redirect('*/index/index');
                        } else {
                            if ($shipmentId) {
                                try {
                                    $shipment = $this->shipmentRepository->get((int)$shipmentId);
                                    $this->api->generateTrackingNumber($order, $quote, $shipment);
                                    $this->helper->sendTrackingEmail($order, $shipment);
                                    $successMessage = __('Time Express Parcels Tracking has been created successfully.');
                                    $this->messageManager->addSuccessMessage($successMessage);
                                } catch (\Exception $e) {
                                    $this->messageManager->addErrorMessage(__('There was an issue while loading shipment for tracking.'));
                                    $this->_redirect('*/index/index');
                                }
                            } else {
                                $errorMessage = __('Please create shipment first to generate tracking.');
                                $this->messageManager->addErrorMessage($errorMessage);
                            }
                            if ($this->getRequest()->getParam('from')) {
                                $orderId = $this->getRequest()->getParam('order');
                                $this->_redirect($this->getUrl('sales/order/view/', ['order_id'=>$orderId]));
                            } else {
                                $this->_redirect('*/index/index');
                            }
                        }
                    } else {
                        throw new MagentoException(__('This order not eligible for Time Express Parcels'));
                    }
                } else {
                    throw new MagentoException(__('Invalid Order'));
                }
            } else {
                throw new MagentoException(__('Invalid Order'));
            }
        } catch (MagentoException $ex) {
            $this->messageManager->addErrorMessage(__('Tracking Creation Failed: ') . $ex->getMessage());
            if ($this->getRequest()->getParam('from')) {
                $orderId = $this->getRequest()->getParam('order');
                $this->_redirect($this->getUrl('sales/order/view/', ['order_id'=>$orderId]));
            } else {
                $this->_redirect('*/index/index');
            }
        }
    }
}
