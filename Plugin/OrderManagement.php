<?php

namespace TimeExpressParcels\TimeExpressParcels\Plugin;

use Magento\Framework\Logger\Monolog;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use TimeExpressParcels\TimeExpressParcels\Helper\Data as TimeExpressParcelsHelper;
use TimeExpressParcels\TimeExpressParcels\Model\Api as TimeExpressParcelsApi;
use TimeExpressParcels\TimeExpressParcels\Model\CreateShipment;

class OrderManagement
{
    /**
     * @var TimeExpressParcelsHelper
     */
    protected $helper;

    /**
     * @var TimeExpressParcelsApi
     */
    protected $api;

    /**
     * @var Monolog
     */
    protected $logger;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var QuoteResourceModel
     */
    protected $quoteResourceModel;

    /**
     * @var CreateShipment
     */
    private $createShipment;

    /**
     * @var ShipmentRepositoryInterface
     */
    private $shipmentRepository;

    /**
     * @param TimeExpressParcelsHelper $helper
     * @param TimeExpressParcelsApi $api
     * @param Monolog $logger
     * @param OrderFactory $orderFactory
     * @param QuoteFactory $quoteFactory
     * @param QuoteResourceModel $quoteResourceModel
     * @param CreateShipment $createShipment
     * @param ShipmentRepositoryInterface $shipmentRepository
     */
    public function __construct(
        TimeExpressParcelsHelper $helper,
        TimeExpressParcelsApi $api,
        Monolog $logger,
        OrderFactory $orderFactory,
        QuoteFactory $quoteFactory,
        QuoteResourceModel $quoteResourceModel,
        CreateShipment $createShipment,
        ShipmentRepositoryInterface $shipmentRepository
    ) {
        $this->helper = $helper;
        $this->api = $api;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->createShipment = $createShipment;
        $this->shipmentRepository = $shipmentRepository;
    }

    public function afterPlace(OrderManagementInterface $subject, OrderInterface $result) {
        $orderId = $result->getId();
        if ($orderId) {
            try {
                $type = $this->helper->getStoreConfig('carriers/timeexpressparcels/type');
                $order = $result;
                $quoteId = $order->getQuoteId();
                if ($order && $quoteId) {
                    $quote = $this->quoteFactory->create();
                    $this->quoteResourceModel->load($quote, $quoteId);
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
                        $this->helper->saveTrackingData($order, $quote);
                        if ($type) {
                            // Magento Order Shipment
                            $shipmentId = 0;
                            if (empty($shipmentId) || $shipmentId == 0) {
                                $shipmentId = $this->createShipment->createOrderShipment($order->getId());
                            }
                            if (empty($shipmentId) || $shipmentId == 0) {
                                $this->api->autoGenerateTrackingNumber($order, $quote);
                                $this->helper->autoSendTrackingEmail($order, $quote);
                            } else {
                                $shipment = $this->shipmentRepository->get((int)$shipmentId);
                                $this->api->generateTrackingNumber($order, $quote, $shipment);
                                $this->helper->sendTrackingEmail($order, $shipment);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->debug('TimeExpressParcels SalesOrderPlaceAfter Exception: ' . $e->getMessage());
            }
        }
        return $result;
    }
}
