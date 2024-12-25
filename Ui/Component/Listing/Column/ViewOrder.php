<?php

namespace TimeExpressParcels\TimeExpressParcels\Ui\Component\Listing\Column;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class ViewOrder
 * @package TimeExpressParcels\TimeExpressParcels\Ui\Component\Listing\Column
 */
class ViewOrder extends Column
{
    const ORDER_VIEW_URL = 'sales/order/view';

    /**
     * @var UrlInterface
     */
    private $urlInterface;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * ViewOrder constructor
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlInterface
     * @param OrderFactory $orderFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface   $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface       $urlInterface,
        OrderFactory       $orderFactory,
        array              $components = [],
        array              $data = []
    ) {
        parent::__construct(
            $context,
            $uiComponentFactory,
            $components,
            $data
        );
        $this->urlInterface = $urlInterface;
        $this->orderFactory = $orderFactory;
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $name = $this->getData('name');
                if (isset($item['timeexpress_id']) || isset($item['entity_id'])) {
                    $order = $this->orderFactory->create()->load($item['quote_id'], 'quote_id');
                    $item[$name] = sprintf(
                        '<a target="_blank" href="%s" >%s</a>',
                        $this->urlInterface->getUrl(
                            self::ORDER_VIEW_URL,
                            ['order_id' => $order->getEntityId()]
                        ),
                        $item[$name]
                    );
                }
            }
        }

        return $dataSource;
    }
}
