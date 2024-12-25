<?php

namespace TimeExpressParcels\TimeExpressParcels\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Convert\OrderFactory as ConvertOrderFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;

class CreateShipment
{
    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var OrderResourceModel
     */
    private $orderResourceModel;

    /**
     * @var \Magento\Sales\Model\Convert\Order
     */
    private $converter;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var ShipmentRepositoryInterface
     */
    private $shipmentRepository;

    /**
     * @param OrderFactory $orderFactory
     * @param OrderResourceModel $orderResourceModel
     * @param ConvertOrderFactory $convertOrderFactory
     * @param ManagerInterface $messageManager
     * @param ShipmentRepositoryInterface $shipmentRepository
     */
    public function __construct(
        OrderFactory        $orderFactory,
        OrderResourceModel  $orderResourceModel,
        ConvertOrderFactory $convertOrderFactory,
        ManagerInterface $messageManager,
        ShipmentRepositoryInterface $shipmentRepository
    ) {
        $this->orderFactory = $orderFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->converter = $convertOrderFactory->create();
        $this->messageManager = $messageManager;
        $this->shipmentRepository = $shipmentRepository;
    }

    /**
     * @param $orderId
     * @return int|null
     * @throws LocalizedException
     */
    public function createOrderShipment($orderId)
    {
        // Load Magento Order by ID
        $order = $this->orderFactory->create();
        $this->orderResourceModel->load($order, $orderId);

        // Check if order can be shipped or has already shipped
        if (!$order->canShip()) {
            $this->messageManager->addErrorMessage(__('You can\'t create shipment for order ID : ' . $orderId));
            return null;
        }

        // Initialize the order shipment object
        $shipment = $this->converter->toShipment($order);

        // Loop through order items
        foreach ($order->getAllItems() as $orderItem) {
            // Check if order item has qty to ship or is virtual
            if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }
            $qtyShipped = $orderItem->getQtyToShip();
            // Create shipment item with qty
            $shipmentItem = $this->converter->itemToShipmentItem($orderItem)->setQty($qtyShipped);
            // Add shipment item to shipment
            $shipment->addItem($shipmentItem);
        }
        // Register shipment
        $shipment->register();
        $shipment->getOrder()->setIsInProcess(true);

        try {
            // Save created shipment and order
            $shipment = $this->shipmentRepository->save($shipment);
            $this->orderResourceModel->save($shipment->getOrder());

            // Send email
//            $this->_objectManager->create('Magento\Shipping\Model\ShipmentNotifier')
//                ->notify($shipment);
            $shipment = $this->shipmentRepository->save($shipment);
            return $shipment->getEntityId();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('There was an error while creating shipment for order ID : ' . $orderId));
            return null;
        }
    }
}
