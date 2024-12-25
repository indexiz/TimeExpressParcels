<?php
namespace TimeExpressParcels\TimeExpressParcels\Helper;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Logger\Monolog;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\Data\ShipmentTrackInterfaceFactory;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use TimeExpressParcels\TimeExpressParcels\Model\OrderFactory as TimeExpressParcelsOrder;
use TimeExpressParcels\TimeExpressParcels\Model\ResourceModel\Order as TimeExpressParcelsOrderResource;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const XML_PATH_EMAIL_RECIPIENT_NAME = 'trans_email/ident_support/name';
    const XML_PATH_EMAIL_RECIPIENT_EMAIL = 'trans_email/ident_support/email';

    public const ENQ_RATE_URL="http://timeexpress.dnsalias.com:880/Mobile/TimeServices.svc/EnqRateAgainstAgent";
    public const CREATE_AWB_URL="http://timeexpress.dnsalias.com:880/Mobile/TimeServices.svc/AWBCreation";
    public const USER_LOGIN_URL="http://timeexpress.dnsalias.com:880/"
            . "Special/TimeServices_Special.svc/Userauthentication";

    public $resource = '';
    protected $configWriter;
    protected $resourceConfig;
    protected $timezone;
    protected $quoteFactory;
    protected $checkoutSession;
    protected $logger;
    protected $timeexpressparcelsOrder;

    protected $_inlineTranslation;
    protected $_transportBuilder;
    protected $storeManager;
    protected $assetRepo;
    protected $curl;

    /**
     * @var ShipmentTrackInterfaceFactory
     */
    private $trackFactory;

    /**
     * @var ShipmentRepositoryInterface
     */
    private $shipmentRepository;

    protected $timeexpressparcelsOrderResource;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\ResourceConnection $resource,
        WriterInterface $configWriter,
        ConfigInterface $resourceConfig,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        QuoteFactory $quoteFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        Monolog $logger,
        TimeExpressParcelsOrder $timeexpressparcelsOrder,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\HTTP\Client\Curl $curl,
        ShipmentTrackInterfaceFactory $trackFactory,
        ShipmentRepositoryInterface $shipmentRepository,
        TimeExpressParcelsOrderResource $timeexpressparcelsOrderResource
    ) {
        parent::__construct($context);
        $this->resource = $resource;
        $this->configWriter = $configWriter;
        $this->resourceConfig = $resourceConfig;
        $this->timezone = $timezone;
        $this->quoteFactory = $quoteFactory;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->timeexpressparcelsOrder = $timeexpressparcelsOrder;
        $this->_inlineTranslation = $inlineTranslation;
        $this->_transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->assetRepo = $assetRepo;
        $this->curl = $curl;
        $this->trackFactory = $trackFactory;
        $this->shipmentRepository = $shipmentRepository;
        $this->timeexpressparcelsOrderResource = $timeexpressparcelsOrderResource;
    }

    public function getStoreConfig($path)
    {
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    public function saveStoreConfig($path, $value)
    {
        $this->configWriter->save($path, $value, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
    }

    public function deleteStoreConfig($path)
    {
        $this->resourceConfig->deleteConfig($path, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
    }

    public function getStoreConfigDb($path)
    {
        $connection= $this->resource->getConnection();
        $table = $this->resource->getTableName('core_config_data');
        $sql = $connection->select()
                ->from($table, ['value'])
                ->where('path=?', $path);
        return $connection->fetchOne($sql);
    }

    public function login($username, $password)
    {
        $status = 0;
        $error = '';

        $login_data = [
            'UserName' => $username,
            'PassWord' => $password
        ];

        $loginData = json_encode($login_data);

        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_ENCODING, '');
        $this->curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->curl->addHeader("Content-Type", "application/json");

        $this->curl->post(self::USER_LOGIN_URL, $loginData);
        $response = $this->curl->getBody();
        if ($response) {
            $response_json = json_decode($response, true);

            if ($response_json['code'] == 1) {
                $client = $response_json['ClientList'][0];

                $tes_user = [
                   'user' => $client['CPerson'],
                   'name' => $client['CustName'],
                   'address' => $client['CustAddress'],
                   'account_no' => $client['CustCode'],
                   'email' => $client['CustEMail'],
                   'phone' => $client['CustPhone'],
                ];

                $this->saveStoreConfig('timeexpressparcels_account_info', json_encode($tes_user));
                $this->saveStoreConfig('timeexpressparcels_account_no', $tes_user['account_no']);

                $status = 1;
            } else {
                $error = __("Credentials doesn't match");
            }
        } else {
            $error = __("Curl Error");
        }

        return ['status' => $status, 'message' => $error];
    }

    public function logout()
    {
        $this->deleteStoreConfig('timeexpressparcels_account_info');
        $this->deleteStoreConfig('timeexpressparcels_account_no');
    }

    public function getMethods()
    {
        $methods = [
            'NOR' => __('Standard Delivery'),
            'SDD' => __('Same Day Delivery'),
        ];
        return $methods;
    }

    public function canDisplay($serviceCode, $request)
    {
        $display = true;
        if ($serviceCode == 'SDD') {
            $destCountry = $request->getDestCountryId();
            if ($destCountry != 'AE') {
                $display = false;
            } else {
                $region = trim($request->getDestRegionCode()  ?? '');
                $region = strtolower($region);
                if ($region == 'abu dhabi' || $region == 'dubai' || $region == 'abudhabi') {
                    $display = true;
                } else {
                    $display = false;
                }

                if (!$display) {
                    $city = trim($request->getDestCity() ?? '');
                    $city = strtolower($city);
                    if ($city == 'abu dhabi' || $city == 'dubai' || $city == 'abudhabi') {
                        $display = true;
                    } else {
                        $display = false;
                    }
                }

                if ($display) {
                    $date = $this->timezone->date();
                    $noon = $date->format('A');
                    if ($noon == 'PM') {
                        $display = false;
                    }
                }
            }
        }
        return $display;
    }

    public function convertWeight($weight)
    {
        $unit = $this->getStoreConfig('general/locale/weight_unit');
        if ($unit == 'lbs' && $weight > 0) {
            $lb_to_kg = 0.453592;
            $weight = $weight * $lb_to_kg;
        }

        return $weight;
    }

    public function getDimensions($weight)
    {
        if ($weight <= 1.5) {
            $w="18";
            $l="34";
            $h="10";
        } elseif ($weight > 1.5 && $weight<=3) {
            $w="32";
            $l="34";
            $h="10";
        } elseif ($weight > 3 && $weight<=7) {
            $w="32";
            $l="34";
            $h="18";
        } elseif ($weight > 7 && $weight<=12) {
            $w="32";
            $l="34";
            $h="34";
        } elseif ($weight > 12 && $weight<=18) {
            $w="36";
            $l="42";
            $h="37";
        } elseif ($weight > 18 && $weight<=25) {
            $w="40";
            $l="48";
            $h="39";
        } else {
            $w="40";
            $l="48";
            $h="39";
        }

        return ['breadth' => $w, 'length' => $l, 'height' => $h];
    }

    public function getTotalPcs($request)
    {
        $total = 0;
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }
                if ($item->getHasChildren()) {
                    foreach ($item->getChildren() as $child) {
                        if (!$child->getProduct()->isVirtual()) {
                            $total += $child->getQty();
                        }
                    }
                } else {
                    $total += $item->getQty();
                }
            }
        }

        return $total;
    }

    public function getGoodsDescription($quote)
    {
        $desccription = '';
        $allVisibleItems = $quote->getAllVisibleItems();
        foreach ($allVisibleItems as $item) {
            $desccription .= $item->getName() . ';';
        }
        return $desccription;
    }

    public function getGoodsDescriptionForShipment($shipment)
    {
        $description = '';
        $allItems = $shipment->getAllItems();
        foreach ($allItems as $item) {
            $description .= $item->getName() . ';';
        }
        return $description;
    }

    public function origCountry($origCountry)
    {
        return $origCountry;
    }

    public function destCountry($destCountry)
    {
        return $destCountry;
    }

    public function convertPriceFromAED($price, $currency = '')
    {
        if (empty($currency)) {
            $quote = $this->getQuote();
            $currency = $quote->getQuoteCurrencyCode();
        }

        if ($currency != 'AED') {
            $rate = $this->getExchangeRates('AED', $currency);
            $price = $price * $rate;
            $price = round($price, 2);
        }
        return $price;
    }

    public function convertPriceToAED($price, $currency)
    {
        if ($currency != 'AED') {
            $rate = $this->getExchangeRates($currency, 'AED');
            $price = $price * $rate;
            $price = round($price, 2);
        }
        return $price;
    }

    public function getExchangeRates($fromCurrency, $toCurrency)
    {
        $apiURL = 'https://api.exchangerate-api.com/v4/latest/' . strtolower($fromCurrency);

        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_ENCODING, '');
        $this->curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $this->curl->addHeader("Content-Type", "application/json");

        $this->curl->get($apiURL);
        $response = $this->curl->getBody();
        if ($response) {
            $response = utf8_encode($response);
            $response_json = json_decode($response, true);

            if (isset($response_json['rates'])) {
                return $response_json['rates'][$toCurrency];
            } else {
                return 1;
            }
        } else {
            $error = __("Curl Error");
            return 1;
        }
    }

    public function getStoreName()
    {
        return $this->storeManager->getStore()->getName();
    }

    public function autoSendTrackingEmail($order, $quote)
    {
        $account = $this->getStoreConfigDb('timeexpressparcels_account_info');
        if ($account) {
            $client = json_decode($account, true);

            $quoteId = $order->getQuoteId();
            $model = $this->timeexpressparcelsOrder->create();
            $this->timeexpressparcelsOrderResource->load($model, $quoteId, 'quote_id');
            if ($model && $model->getId() > 0) {
                $this->timeexpressparcelsOrderResource->load($model, $model->getId());
                $awbNo =  $model->getAwbno();

                if ($awbNo) {
                    $this->_inlineTranslation->suspend();

                    $sentToEmail = $this->getStoreConfig(
                        'trans_email/ident_general/email',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    );
                    $sentToName = $this->getStoreConfig(
                        'trans_email/ident_general/name',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    );

                    $sender = [
                        'name' => $sentToName,
                        'email' => $sentToEmail
                    ];

                    $logo = $this->assetRepo->getUrl("TimeExpressParcels_TimeExpressParcels::images/logo-purple.png");

                    $transport = $this->_transportBuilder
                        ->setTemplateIdentifier('timeexpressparcels_track_template')
                        ->setTemplateOptions(
                            [
                                'area' => 'frontend',
                                'store' => $this->storeManager->getStore()->getId()
                            ]
                        )
                        ->setTemplateVars([
                            'timeexpressparcels_logo'  => $logo,
                            'awbno'  => $awbNo,
                            'account_name'  => $client['name'],
                            'account_no'  => $client['account_no'],
                        ])
                        ->setFromByScope($sender)
                        ->addTo('lkurisinkal@timexpress.ae', 'lkurisinkal Timexpress')
                        ->addTo('magento@timexpress.ae', 'magento Timexpress')
                        ->addCc($sentToEmail, $sentToName)
                        ->addCc(
                            $order->getCustomerEmail(),
                            $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname()
                        )
                        ->getTransport();

                    $transport->sendMessage();

                    $this->_inlineTranslation->resume();
                }
            }
        }
    }

    public function sendTrackingEmail($order, $shipment)
    {
        $account = $this->getStoreConfigDb('timeexpressparcels_account_info');
        if ($account) {
            $client = json_decode($account, true);

            if ($shipment) {
                $awbNo =  $shipment->getTracksCollection()->getLastItem()->getTrackNumber();

                if ($awbNo) {
                    $this->_inlineTranslation->suspend();

                    $sentToEmail = $this->getStoreConfig(
                        'trans_email/ident_general/email',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    );
                    $sentToName = $this->getStoreConfig(
                        'trans_email/ident_general/name',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    );

                    $sender = [
                        'name' => $sentToName,
                        'email' => $sentToEmail
                    ];

                    $logo = $this->assetRepo->getUrl("TimeExpressParcels_TimeExpressParcels::images/logo-purple.png");

                    $transport = $this->_transportBuilder
                    ->setTemplateIdentifier('timeexpressparcels_track_template')
                    ->setTemplateOptions(
                        [
                            'area' => 'frontend',
                            'store' => $this->storeManager->getStore()->getId()
                        ]
                    )
                    ->setTemplateVars([
                        'timeexpressparcels_logo'  => $logo,
                        'awbno'  => $awbNo,
                        'account_name'  => $client['name'],
                        'account_no'  => $client['account_no'],
                    ])
                    ->setFromByScope($sender)
                    ->addTo('magento@timexpress.ae', 'magento Timexpress')
                    ->addCc($sentToEmail, $sentToName)
                    ->getTransport();

                    $transport->sendMessage();
                    // Now send email to customers with a different template
                    $this->_transportBuilder
                    ->setTemplateIdentifier('customer_special_instructions_template') // Use your new template identifier
                    ->setTemplateOptions(
                        [
                            'area' => 'frontend',
                            'store' => $this->storeManager->getStore()->getId()
                        ]
                    )
                    ->setTemplateVars([
                        'timeexpressparcels_logo' => $logo,
                        'awbno' => $awbNo,
                        'account_name' => $client['name'],
                        'account_no' => $client['account_no'],
                        // Add any other variables needed for the new template
                    ])
                    ->setFromByScope($sender)
                    ->addTo(
                        $order->getCustomerEmail(),
                        $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname()
                    )
                    ->getTransport()
                    ->sendMessage();

                    $this->_inlineTranslation->resume();
                }
            }
        }
    }

    public function saveTrackingData($order, $quote)
    {
        $quoteId = $quote->getId();
        $model = $this->timeexpressparcelsOrder->create();
        $this->timeexpressparcelsOrderResource->load($model, $quoteId, 'quote_id');
        $saved = false;
        if ($model && $model->getId() > 0) {
            $saved = true;
        }
        if (!$saved) {
            $shippingMethod = $order->getShippingMethod();
            $serviceCode = str_replace('timeexpressparcels_', '', $shippingMethod);

            $date = $this->timezone->date();
            $createdAt = $date->format('Y-m-d H:i:s');

            $formData = [
                'quote_id' => $quoteId,
                'increment_id' => $order->getIncrementId(),
                'customer_name' => $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
                'created_at' => $createdAt,
                'order_total' => $order->getGrandTotal(),
                'shipping_total' => $order->getShippingAmount(),
                'order_currency' => $order->getOrderCurrencyCode(),
                'service_type' => $serviceCode,
            ];
            $model->setData($formData);
            $this->timeexpressparcelsOrderResource->save($model);
        }
    }

    public function updateTrackingData($order, $quote, $awbNo)
    {
        $quoteId = $quote->getId();
        $model = $this->timeexpressparcelsOrder->create();
        $this->timeexpressparcelsOrderResource->load($model, $quoteId, 'quote_id');
        if ($model && $model->getId() > 0) {
            $this->timeexpressparcelsOrderResource->load($model, $model->getId());
            $model->setAwbno($awbNo);
            $this->timeexpressparcelsOrderResource->save($model);
        }
    }

    public function updateShipmentTrackingData($shipment, $awbNo)
    {
        if ($shipment) {
            $track = $this->trackFactory->create()->setNumber(
                $awbNo
            )->setCarrierCode(
                'custom'
            )->setTitle(
                'Time Express Parcels'
            );
            $shipment->addTrack($track);
            $this->shipmentRepository->save($shipment);
        }
    }
}
