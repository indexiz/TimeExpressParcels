<?php

namespace TimeExpressParcels\TimeExpressParcels\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Validator\Exception as MagentoException;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use TimeExpressParcels\TimeExpressParcels\Helper\Data as TimeExpressParcelsHelper;
use TimeExpressParcels\TimeExpressParcels\Model\Api as TimeExpressParcelsApi;
use TimeExpressParcels\TimeExpressParcels\Model\CreateShipment;
use TimeExpressParcels\TimeExpressParcels\Model\OrderFactory as TimeExpressParcelsOrder;
use TimeExpressParcels\TimeExpressParcels\Model\ResourceModel\Order\CollectionFactory;
use TimeExpressParcels\TimeExpressParcels\Model\ResourceModel\Order\Grid\CollectionFactory as GridCollectionFactory;
use TimeExpressParcels\TimeExpressParcels\Ui\Component\MassAction\Filter;

/**
 * Class massTrack
 * @package TimeExpressParcels\TimeExpressParcels\Controller\Adminhtml\Index
 */
class massTrack extends Action
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var TimeExpressParcelsHelper
     */
    protected $helper;

    /**
     * @var TimeExpressParcelsApi
     */
    protected $api;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var QuoteResourceModel
     */
    protected $quoteResourceModel;

    /**
     * @var TimeExpressParcelsOrder
     */
    protected $timeexpressparcelsOrder;

    /**
     * @var GridCollectionFactory
     */
    private $gridCollectionFactory;

    /**
     * @var ShipmentRepositoryInterface
     */
    private $shipmentRepository;

    /**
     * @var CreateShipment
     */
    private $createShipment;

    /**
     * massTrack constructor
     *
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param Filter $filter
     * @param TimeExpressParcelsHelper $helper
     * @param TimeExpressParcelsApi $api
     * @param OrderFactory $orderFactory
     * @param OrderResourceModel $orderResourceModel
     * @param QuoteFactory $quoteFactory
     * @param QuoteResourceModel $quoteResourceModel
     * @param TimeExpressParcelsOrder $timeexpressparcelsOrder
     * @param GridCollectionFactory $gridCollectionFactory
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param CreateShipment $createShipment
     */
    public function __construct(
        Context                     $context,
        CollectionFactory           $collectionFactory,
        Filter                      $filter,
        TimeExpressParcelsHelper    $helper,
        TimeExpressParcelsApi       $api,
        OrderFactory                $orderFactory,
        OrderResourceModel          $orderResourceModel,
        QuoteFactory                $quoteFactory,
        QuoteResourceModel          $quoteResourceModel,
        TimeExpressParcelsOrder     $timeexpressparcelsOrder,
        GridCollectionFactory       $gridCollectionFactory,
        ShipmentRepositoryInterface $shipmentRepository,
        CreateShipment              $createShipment
    ) {
        parent::__construct($context);
        $this->collectionFactory = $collectionFactory;
        $this->filter = $filter;
        $this->helper = $helper;
        $this->api = $api;
        $this->orderFactory = $orderFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->timeexpressparcelsOrder = $timeexpressparcelsOrder;
        $this->gridCollectionFactory = $gridCollectionFactory;
        $this->shipmentRepository = $shipmentRepository;
        $this->createShipment = $createShipment;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->gridCollectionFactory->create());

        if (!$collection->getSize()) {
            $this->messageManager->addErrorMessage(__('Please select order(s) to generate tracking.'));
            return $this->resultRedirectFactory->create()->setPath($this->_redirect->getRefererUrl());
        }
        $size = $collection->getSize();
        $alreadyProcessedOrders  = [];
        foreach ($collection->getItems() as $item) {
            if ($item->getData('tracking_status') == '1') {
                $alreadyProcessedOrders[] = $item->getData('shipment_id');
                continue;
            }
            $quoteId = $item->getData('quote_id');
            try {
                $quoteId = (int)strip_tags(trim($quoteId));
                if ($quoteId) {
                    // Magento Order
                    $order = $this->orderFactory->create();
                    $this->orderResourceModel->load($order, $quoteId, 'quote_id');
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
                            try {
                                if (!str_contains($item->getData('timeexpress_id'), '_')) {
                                    //continue; // Don't skip - Create shipment
                                    // Magento Order Shipment
                                    $shipmentId = 0;
                                    if (empty($shipmentId) || $shipmentId == 0) {
                                        $shipmentId = $this->createShipment->createOrderShipment($order->getId());
                                    }
                                    if (empty($shipmentId) || $shipmentId == 0) {
                                        continue;
                                    } else {
                                        $item->setData('timeexpress_id', $item->getData('timeexpress_id') . '_' . $shipmentId);
                                    }
                                }
                                $shipment = $this->shipmentRepository->get((int)explode('_', $item->getData('timeexpress_id'))[1]);
                                $this->api->generateTrackingNumber($order, $quote, $shipment);
                                $this->helper->sendTrackingEmail($order, $shipment);
                            } catch (\Exception $e) {
                                $this->messageManager->addErrorMessage(__('There was an issue while loading shipment for tracking.'));
                                $this->_redirect('*/index/index');
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
                $this->_redirect('*/index/index');
            }
        }
        $this->messageManager->addSuccessMessage(
            __('Selected record(s) have been processed to create Time Express Parcels Tracking.', $size)
        );
//        if (count($alreadyProcessedOrders) > 0) {
//            $errorMessage = __('%1 out of %2 record(s) have already been processed for Time Express Parcels Tracking.', count($alreadyProcessedOrders), $size);
//            $this->messageManager->addErrorMessage($errorMessage);
//        }
        return $this->resultRedirectFactory->create()->setPath($this->_redirect->getRefererUrl());
    }
}
