<?php
declare(strict_types=1);

namespace TimeExpressParcels\TimeExpressParcels\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Weee\Block\Item\Price\Renderer;
use TimeExpressParcels\TimeExpressParcels\Helper\Data as TimeExpressParcelsHelper;

class CalculateValueOfShipment implements ObserverInterface
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
     * @var Renderer
     */
    private $priceRenderer;

    /**
     * @var TimeExpressParcelsHelper
     */
    private $helper;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var QuoteResourceModel
     */
    private $quoteResourceModel;

    /**
     * @var ShipmentRepositoryInterface
     */
    private $shipmentRepository;

    /**
     * @param OrderFactory $orderFactory
     * @param OrderResourceModel $orderResourceModel
     * @param Renderer $priceRenderer
     * @param TimeExpressParcelsHelper $helper
     * @param QuoteFactory $quoteFactory
     * @param QuoteResourceModel $quoteResourceModel
     * @param ShipmentRepositoryInterface $shipmentRepository
     */
    public function __construct(
        OrderFactory $orderFactory,
        OrderResourceModel $orderResourceModel,
        Renderer $priceRenderer,
        TimeExpressParcelsHelper $helper,
        QuoteFactory $quoteFactory,
        QuoteResourceModel $quoteResourceModel,
        ShipmentRepositoryInterface $shipmentRepository
    ) {
        $this->orderFactory = $orderFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->priceRenderer = $priceRenderer;
        $this->helper = $helper;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->shipmentRepository = $shipmentRepository;
    }

    public function execute(EventObserver $observer)
    {
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();
        if ($shipment->getData('tep_shipment_value')) {
            return;
        }

        // Magento Order
        $order = $this->orderFactory->create();
        $this->orderResourceModel->load($order, $shipment->getOrderId());

        // Magento Quote
//        $quote = $this->quoteFactory->create();
//        $this->quoteResourceModel->load($quote, $order->getQuoteId());
//        $currency = $quote->getQuoteCurrencyCode();

        // calculate value of shipment items
        $valueOfShipment = 0;
        foreach ($shipment->getAllItems() as $shipmentItem) {
            $orderItem = $order->getItemById($shipmentItem->getOrderItemId());
            $singleRowTotal = $this->priceRenderer->getTotalAmount($orderItem)/$orderItem->getQtyOrdered();
            $valueOfShipment += ($singleRowTotal * $shipmentItem->getQty());
        }
        //$valueOfShipment = round($this->helper->convertPriceToAED($valueOfShipment, $currency), 2);
        $shipment->setData('tep_shipment_value', $valueOfShipment);
        $this->shipmentRepository->save($shipment);
    }
}
