<?php

namespace TimeExpressParcels\TimeExpressParcels\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class TrackingStatus
 * @package TimeExpressParcels\TimeExpressParcels\Model\Config\Source
 */
class TrackingStatus implements OptionSourceInterface
{
    const STATUS_PENDING = 0;
    const STATUS_PROCESSED = 1;
    const STATUS_OPTIONS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PROCESSED => 'Processed',
    ];

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $statusArray = self::STATUS_OPTIONS;
        $result = [];
        foreach ($statusArray as $key => $value) {
            $result[] = [
                'label' => $value,
                'value' => $key
            ];
        }
        return $result;
    }
}
