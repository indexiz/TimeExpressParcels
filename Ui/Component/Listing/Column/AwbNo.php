<?php

namespace TimeExpressParcels\TimeExpressParcels\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class AwbNo
 * @package TimeExpressParcels\TimeExpressParcels\Ui\Component\Listing\Column
 */
class AwbNo extends Column
{
    const ROW_EDIT_URL = 'timeexpressparcels/order/track';

    /**
     * @var UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var string
     */
    private $_editUrl;

    /**
     * PageAction constructor.
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     * @param string $editUrl
     */
    public function __construct(
        ContextInterface   $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface       $urlBuilder,
        array              $components = [],
        array              $data = [],
        string             $editUrl = self::ROW_EDIT_URL
    ) {
        parent::__construct(
            $context,
            $uiComponentFactory,
            $components,
            $data
        );
        $this->_urlBuilder = $urlBuilder;
        $this->_editUrl = $editUrl;
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $name = $this->getData('name');
                if (isset($item['timeexpress_id']) || isset($item['entity_id'])) {
                    $awbNo =  $item['track_number'] ?? $item['awbno'];
                    if ($awbNo) {
                        $item[$name] = $awbNo . " : " . sprintf(
                            '<a target="_blank" href="%s" >%s</a>',
                            $this->_urlBuilder->getUrl(
                                "https://www.timexpress.ae/track/" . $awbNo
                            ),
                            __('Track Here')
                        );
                    } else {
                        if (isset($item['timeexpress_id']) && str_contains($item['timeexpress_id'], '_')) {
                            $requestedIds = explode('_', $item['timeexpress_id']);
                            $item[$name] = sprintf(
                                '<a href="%s" >%s</a>',
                                $this->_urlBuilder->getUrl(
                                    $this->_editUrl,
                                    ['order_id' => $item['quote_id'], 'shipment_id' => $requestedIds[1]]
                                ),
                                __('Create Tracking Number')
                            );
                        } else {
                            $item[$name] = sprintf(
                                '<a href="%s" >%s</a>',
                                $this->_urlBuilder->getUrl(
                                    $this->_editUrl,
                                    ['order_id' => $item['quote_id'], 'shipment_id' => '']
                                ),
                                __('Create Tracking Number')
                            );
                        }

                    }
                }
            }
        }
        return $dataSource;
    }
}
