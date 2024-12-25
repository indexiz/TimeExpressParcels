<?php

namespace TimeExpressParcels\TimeExpressParcels\Controller\Adminhtml\Index;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\App\Action;

/**
 * Class Index
 * @package TimeExpressParcels\TimeExpressParcels\Controller\Adminhtml\Index
 */
class Index extends Action
{
    public const HEADING_ONE = 'Bulk & Automatic Orders: ';
    public const MESSAGE_ONE = 'Shipments are automatically created when a tracking number is generated for normal orders.';
    public const HEADING_TWO = 'Multiple/Partial Shipments: ';
    public const MESSAGE_TWO = 'Manually create shipments before generating the tracking number.';

    /**
     * @var bool|PageFactory
     */
    protected $resultPageFactory;

    /**
     * Index constructor
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $this->messageManager->addComplexNoticeMessage(
            'adminTimeExpressMessage',
            [
                'heading' => __(self::HEADING_ONE)->getText(),
                'message' => __(self::MESSAGE_ONE)->getText()
            ]
        );
        $this->messageManager->addComplexNoticeMessage(
            'adminTimeExpressMessage',
            [
                'heading' => __(self::HEADING_TWO)->getText(),
                'message' => __(self::MESSAGE_TWO)->getText()
            ]
        );
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('TimeExpressParcels_TimeExpressParcels::order');
        $resultPage->getConfig()->getTitle()->prepend(__('Time Express Parcels Orders'));
        return $resultPage;
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('TimeExpressParcels_TimeExpressParcels::order');
    }
}
