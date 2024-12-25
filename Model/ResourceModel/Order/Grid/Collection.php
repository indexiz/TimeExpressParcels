<?php

namespace TimeExpressParcels\TimeExpressParcels\Model\ResourceModel\Order\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface as Logger;
use TimeExpressParcels\TimeExpressParcels\Model\ResourceModel\Order as TimeExpressParcelsResourceModel;

/**
 * Class Collection
 * @package TimeExpressParcels\TimeExpressParcels\Model\ResourceModel\Order\Grid
 */
class Collection extends SearchResult
{
    /**
     * Collection constructor
     *
     * @param EntityFactory $entityFactory
     * @param Logger $logger
     * @param FetchStrategy $fetchStrategy
     * @param EventManager $eventManager
     * @param string $mainTable
     * @param null|string $resourceModel
     * @throws LocalizedException
     */
    public function __construct(
        EntityFactory    $entityFactory,
        Logger           $logger,
        FetchStrategy    $fetchStrategy,
        EventManager     $eventManager,
        $mainTable = "timeexpressparcels_order",
        $resourceModel = TimeExpressParcelsResourceModel::class
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel
        );
    }

    protected function _initSelect()
    {
        parent::_initSelect();

        $this->addFieldToSelect(['quote_id', 'order_id', 'increment_id', 'customer_name', 'created_at', 'order_total', 'shipping_total', 'order_currency', 'service_type', 'awbno'])
            ->addFilterToMap('entity_id', 'timeexpress_id')
            ->addFilterToMap('quote_id', 'main_table.quote_id')
            ->addFilterToMap('order_id', 'main_table.order_id')
            ->addFilterToMap('increment_id', 'main_table.increment_id')
            ->addFilterToMap('customer_name', 'main_table.customer_name')
            ->addFilterToMap('created_at', 'main_table.created_at')
            ->addFilterToMap('order_date', 'sales_order.created_at')
            ->addFilterToMap('order_total', 'main_table.order_total')
            ->addFilterToMap('shipping_total', 'main_table.shipping_total')
            ->addFilterToMap('order_currency', 'main_table.order_currency')
            ->addFilterToMap('service_type', 'main_table.service_type')
            ->addFilterToMap('status', 'sales_order.status')
            ->addFilterToMap('tep_shipment_value', 'shipment.tep_shipment_value')
            ->addExpressionFieldToSelect('shipment_id', "IF(shipment.increment_id != '' OR shipment.increment_id is not null, shipment.increment_id, null)", [])
            ->addExpressionFieldToSelect('awbno', "IF(track.track_number != '' OR track.track_number is not null, track.track_number, main_table.awbno)", [])
            ->addExpressionFieldToSelect('tracking_status', "IF(main_table.awbno = '' OR main_table.awbno is null, IF(track.track_number is null, 0, 1), 1)", [])
            ->addExpressionFieldToSelect('timeexpress_id', "CONCAT_WS('_', main_table.entity_id, shipment.entity_id)", [])
            ->addFilterToMap('awbno', new \Zend_Db_Expr("IF(track.track_number != '' OR track.track_number is not null, track.track_number, main_table.awbno)"))
            ->addFilterToMap('tracking_status', new \Zend_Db_Expr("IF(main_table.awbno = '' OR main_table.awbno is null, IF(track.track_number is null, 0, 1), 1)"))
            ->addFilterToMap('timeexpress_id', new \Zend_Db_Expr("CONCAT_WS('_', main_table.entity_id, shipment.entity_id)"))
            ->addFilterToMap('shipment_id', new \Zend_Db_Expr("IF(shipment.increment_id != '' OR shipment.increment_id is not null, shipment.increment_id, null)"))
            ->getSelect()
            ->joinLeft(
                ['sales_order' => 'sales_order'],
                'main_table.increment_id = sales_order.increment_id',
                [
                    'status',
                    'order_date' => 'sales_order.created_at'
                ]
            )
            ->joinLeft(
                ['shipment' => 'sales_shipment'],
                'sales_order.entity_id = shipment.order_id',
                [
                    'shipment_id' => 'shipment.increment_id',
                    'tep_shipment_value' => 'shipment.tep_shipment_value',
                    'timeexpress_id' => new \Zend_Db_Expr("CONCAT_WS('_', main_table.entity_id, shipment.entity_id)")
                ]
            )
            ->joinLeft(
                ['track' => 'sales_shipment_track'],
                'track.parent_id = shipment.entity_id',
                [
                    'track_number' => 'track.track_number'
                ]
            );

        $columns = $this->getSelect()->getPart(Select::COLUMNS);
        $columns = array_splice($columns, 1);
        $this->getSelect()->setPart(Select::COLUMNS, $columns);

        return $this;
    }
}
