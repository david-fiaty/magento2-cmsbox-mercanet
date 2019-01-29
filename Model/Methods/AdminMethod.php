<?php
/**
 * Cmsbox.fr Magento 2 Payment module (https://www.cmsbox.fr)
 *
 * Copyright (c) 2017 Cmsbox.fr (https://www.cmsbox.fr)
 * Author: David Fiaty | contact@cmsbox.fr
 *
 * License GNU/GPL V3 https://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace Cmsbox\Mercanet\Model\Methods;

use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Cmsbox\Mercanet\Gateway\Config\Core;
use Cmsbox\Mercanet\Helper\Tools;
use Cmsbox\Mercanet\Gateway\Processor\Connector;
use Cmsbox\Mercanet\Gateway\Config\Config;

class AdminMethod extends AbstractMethod {

    protected $_formBlockType = \Cmsbox\Mercanet\Block\Adminhtml\Payment\Form::class;
    protected $_code;
    protected $_isInitializeNeeded = true;
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCancel = true;
    protected $_canCapturePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $backendAuthSession;
    protected $cart;
    protected $urlBuilder;
    protected $_objectManager;
    protected $invoiceSender;
    protected $transactionFactory;
    protected $customerSession;
    protected $checkoutSession;
    protected $checkoutData;
    protected $quoteRepository;
    protected $quoteManagement;
    protected $orderSender;
    protected $sessionQuote;
    protected $config;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Backend\Model\Auth\Session $backendAuthSession,
        Config $config,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\ObjectManagerInterface $objectManager, 
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Helper\Data $checkoutData,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Backend\Model\Session\Quote $sessionQuote,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,

        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->urlBuilder         = $urlBuilder;
        $this->backendAuthSession = $backendAuthSession;
        $this->cart               = $cart;
        $this->_objectManager     = $objectManager;
        $this->invoiceSender      = $invoiceSender;
        $this->transactionFactory = $transactionFactory;
        $this->customerSession    = $customerSession;
        $this->checkoutSession    = $checkoutSession;
        $this->checkoutData       = $checkoutData;
        $this->quoteRepository    = $quoteRepository;
        $this->quoteManagement    = $quoteManagement;
        $this->orderSender        = $orderSender;
        $this->sessionQuote       = $sessionQuote;
        $this->config             = $config;
        $this->_code              = Core::methodId(get_class());
    }

    /**
     * Check whether method is available
     *
     * @param \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote) && null !== $quote;
    }

    /**
     * Check whether method is active
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (int) $this->config->params[$this->_code][Connector::KEY_ACTIVE] == 1;
    }

    /**
     * Prepare the request data.
     */  
    public static function getRequestData($config, $methodId, $cardData = null, $entity = null) {
        // Get the order entity
        $entity = ($entity) ? $entity : $config->cart->getQuote();

        // Get the vendor instance
        $fn = "\\" . $config->params[$methodId][Core::KEY_VENDOR];
        $paymentRequest = new $fn($config->getSecretKey());

        // Prepare the request
        $paymentRequest = new $fn($config->getSecretKey());
        $paymentRequest->setMerchantId($config->getMerchantId());
        $paymentRequest->setInterfaceVersion($config->params[$methodId][Core::KEY_INTERFACE_VERSION_CHARGE]);
        $paymentRequest->setKeyVersion($config->params[Core::moduleId()][Core::KEY_VERSION]);
        $paymentRequest->setAmount($config->formatAmount($entity->getGrandTotal()));
        $paymentRequest->setCurrency(Tools::getCurrencyCode($entity));
        $paymentRequest->setCardNumber($cardData[Core::KEY_CARD_NUMBER]);
        $paymentRequest->setCardExpiryDate($cardData[Core::KEY_CARD_YEAR] . $cardData[Core::KEY_CARD_MONTH]);
        $paymentRequest->setCardCSCValue($cardData[Core::KEY_CARD_CVV]);
        $paymentRequest->setTransactionReference($config->createTransactionReference());
        $paymentRequest->setCaptureDay((string) $config->params[$methodId][Connector::KEY_CAPTURE_DAY]);
        $paymentRequest->setCaptureMode($config->params[$methodId][Connector::KEY_CAPTURE_MODE]);
        $paymentRequest->setOrderId(Tools::getIncrementId($entity));
        $paymentRequest->setUrl($config->params[$methodId]['api_url_test']); // Todo- add prod detection linked to config
        $paymentRequest->setPspRequest($config->params[$methodId][Core::KEY_CHARGE_SUFFIX]);
        $paymentRequest->setOrderChannel("INTERNET");
        $paymentRequest->setCustomerContactEmail($entity->getCustomerEmail());

        // Execute the request
        $paymentRequest->executeRequest();

        // Get the response
        $paymentRequest->getResponseRequest();

        // Return the request object
        return $paymentRequest;
    }

    /**
     * Checks if a response is valid.
     *
     * @return bool
     */  
    public static function isValidResponse($config, $methodId, $asset) {
        return $asset->isValid();
    }

    /**
     * Checks if a response is success.
     *
     * @return bool
     */  
    public static function isSuccessResponse($config, $methodId, $asset) {
        return $asset->isValid();
    }
    
    /**
     * Gets a transaction id.
     *
     * @return bool
     */  
    public static function getTransactionId($config, $paymentObject) {
        return $paymentObject->getParam($config->base[Connector::KEY_TRANSACTION_ID_FIELD]);
    }
    
    /**
     * Determines if the method is active on frontend.
     *
     * @return bool
     */
    public static function isFrontend($config, $methodId) {
        return false;
    }
}