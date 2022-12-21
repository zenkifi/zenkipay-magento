<?php

/**
 * Zenki_Zenkipay payment method model
 *
 * @category    Payments
 * @package     Zenki_Zenkipay
 * @author      Federico Balderas
 * @copyright   Zenki (https://zenki.fi)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Zenki\Zenkipay\Model;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Customer\Model\Session as CustomerSession;

class Zenkipay extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'zenki_zenkipay';

    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canOrder = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canAuthorize = true;
    protected $_canVoid = true;
    protected $_isOffline = true;
    protected $is_sandbox;
    protected $api_key;
    protected $secret_key;
    protected $api_url;
    protected $scopeConfig;
    protected $logger;
    protected $_storeManager;
    protected $customerSession;
    protected $configWriter;
    protected $webhook_signing_secret;

    /**
     *
     * @param StoreManagerInterface $storeManager
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param Logger $logger
     * @param ModuleListInterface $moduleList
     * @param TimezoneInterface $localeDate
     * @param CountryFactory $countryFactory
     * @param LoggerInterface $logger_interface
     * @param CustomerSession $customerSession
     * @param array $data
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        Logger $logger,
        CountryFactory $countryFactory,
        LoggerInterface $logger_interface,
        CustomerSession $customerSession,
        array $data = []
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, null, null, $data);

        $this->customerSession = $customerSession;

        $this->_storeManager = $storeManager;
        $this->logger = $logger_interface;

        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->country_factory = $countryFactory;

        $this->is_active = $this->getConfigData('active');
        $this->is_sandbox = $this->getConfigData('is_sandbox');
        $this->api_key = $this->getConfigData('api_key');
        $this->secret_key = $this->getConfigData('secret_key');
        $this->webhook_signing_secret = $this->getConfigData('webhook_signing_secret');
    }

    /**
     * Order payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        try {
            // Actualiza el estado de la orden
            $state = \Magento\Sales\Model\Order::STATE_NEW;
            $order->setState($state)->setStatus($state);
            $order->save();
        } catch (\Exception $e) {
            $this->debugData(['exception' => $e->getMessage()]);
            $this->_logger->error(__($e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__($this->error($e)));
        }

        $payment->setSkipOrderProcessing(true);
        return $this;
    }

    /**
     * @return string
     */
    public function getBaseUrlStore()
    {
        return $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
    }

    /**
     * @return boolean
     */
    public function isLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote);
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->api_key;
    }

    /**
     * @return string
     */
    public function getSecretKey()
    {
        return $this->secret_key;
    }

    /**
     * @return string
     */
    public function isSandbox()
    {
        return $this->is_sandbox;
    }

    /**
     * @return string
     */
    public function getWebhookSigningSecret()
    {
        return $this->webhook_signing_secret;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->_code;
    }

    /**
     * Validates keys
     *
     * @return \Exception|void
     */
    public function validateSettings()
    {
        $response = $this->validateZenkipayKey();
        if (!$response) {
            throw new \Magento\Framework\Validator\Exception(__("Your credentials are incorrect or don't match with selected environment."));
        }

        return;
    }

    /**
     * Checks if the Zenkipay credentials are valid
     *
     * @return boolean
     */
    protected function validateZenkipayKey()
    {
        $zenkipay = new \Zenkipay\Sdk($this->api_key, $this->secret_key);
        $result = $zenkipay->getAccessToken();

        $this->logger->info('Zenkipay - getAccessToken => ' . $result);

        // Se valida que la respuesta sea un JWT
        $regex = preg_match('/^([a-zA-Z0-9_=]+)\.([a-zA-Z0-9_=]+)\.([a-zA-Z0-9_\-\+\/=]*)/', $result);
        if ($regex != 1) {
            return false;
        }

        $merchant = $zenkipay->merchants()->me();
        if ($merchant->apiEnvironment == 'DEV' && !$this->is_sandbox) {
            return false;
        }

        return true;
    }

    public function handleTrackingNumber($zenki_order_id, $data)
    {
        try {
            $this->logger->info('Zenkipay - handleTrackingNumber => ' . json_encode($data));

            $zenkipay = new \Zenkipay\Sdk($this->api_key, $this->secret_key);
            $zenkipay->trackingNumbers()->create($zenki_order_id, $data);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Zenkipay - handleTrackingNumber: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Refund capture
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|Payment $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $zenki_order_id = $order->getExtOrderId();

        if ($amount <= 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for refund.'));
        }

        try {
            $data = ['reason' => 'Refund request originated by Magento.'];
            $zenkipay = new \Zenkipay\Sdk($this->api_key, $this->secret_key);
            $result = $zenkipay->refunds()->create($zenki_order_id, $data);

            $this->logger->info('Zenkipay - refund => ' . json_encode($data));
            $this->logger->info('Zenkipay - refund => ' . $result);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }

        return $this;
    }
}
