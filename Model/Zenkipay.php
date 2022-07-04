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
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canAuthorize = true;
    protected $_canVoid = true;
    protected $is_sandbox;
    protected $pk;
    protected $sandbox_pk;
    protected $live_pk;
    protected $base_url;
    protected $scopeConfig;
    protected $logger;
    protected $_storeManager;
    protected $customerSession;
    protected $configWriter;

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

        $sandbox_url = 'https://dev-gateway.zenki.fi';
        $url = 'https://uat-gateway.zenki.fi';

        $this->customerSession = $customerSession;

        $this->_storeManager = $storeManager;
        $this->logger = $logger_interface;

        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->country_factory = $countryFactory;

        $this->is_active = $this->getConfigData('active');
        $this->is_sandbox = $this->getConfigData('is_sandbox');
        $this->sandbox_pk = $this->getConfigData('sandbox_pk');
        $this->live_pk = $this->getConfigData('live_pk');
        $this->rsa_private_key = $this->getConfigData('rsa_private_key');
        $this->pk = $this->is_sandbox ? $this->sandbox_pk : $this->live_pk;
        $this->base_url = $this->is_sandbox ? $sandbox_url : $url;
    }

    /**
     * Assign corresponding data
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);

        $infoInstance = $this->getInfoInstance();
        $additionalData = $data->getData('additional_data') != null ? $data->getData('additional_data') : $data->getData();

        $infoInstance->setAdditionalInformation('zenkipay_order_id', isset($additionalData['zenkipay_order_id']) ? $additionalData['zenkipay_order_id'] : null);

        return $this;
    }

    /**
     * Send capture request to gateway
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        /** @var \Magento\Sales\Model\Order\Address $billing */

        $this->logger->debug('#capture', ['$order_id' => $order->getIncrementId(), '$trx_id' => $payment->getLastTransId(), '$status' => $order->getStatus(), '$amount' => $amount]);

        if ($amount <= 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for capture.'));
        }

        $zenkipay_order_id = $this->getInfoInstance()->getAdditionalInformation('zenkipay_order_id');

        try {
            $payment->setAmount($amount);
            $payment->setTransactionId($zenkipay_order_id);

            // Registra el ID de la transacción
            $order->setExtOrderId($zenkipay_order_id);
            $order->save();

            $this->updateZenkipayOrder($zenkipay_order_id, $order->getId());
        } catch (\Exception $e) {
            $this->debugData(['exception' => $e->getMessage()]);
            $this->logger->error(__($e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }

        return $this;
    }

    public function getBaseUrlStore()
    {
        return $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
    }

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

    public function getPublicKey()
    {
        return $this->pk;
    }

    /**
     * @return string
     */
    public function isSandbox()
    {
        return $this->is_sandbox;
    }

    public function getRsaPrivateKey()
    {
        return $this->rsa_private_key;
    }

    public function getCode()
    {
        return $this->_code;
    }

    public function validateSettings()
    {
        $response = $this->validateZenkipayKey();
        if (!$response) {
            throw new \Magento\Framework\Validator\Exception(__('Something went wrong while saving this configuration, your Zenkipay key is incorrect.'));
        }

        if (!$this->validateRSAPrivateKey($this->rsa_private_key)) {
            throw new \Magento\Framework\Validator\Exception(__('Invalid RSA private key has been provided.'));
        }

        return;
    }

    /**
     * Checks if the plain RSA private key is valid
     *
     * @param string $plain_rsa_private_key Plain RSA private key
     *
     * @return boolean
     */
    protected function validateRSAPrivateKey(string $plain_rsa_private_key)
    {
        try {
            $private_key = openssl_pkey_get_private($plain_rsa_private_key);

            if (is_object($private_key)) {
                $public_key = openssl_pkey_get_details($private_key);

                if (is_array($public_key) && isset($public_key['key'])) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Zenkipay - validateRSAPrivateKey: ', ['msg' => $e->getMessage(), 'traceAsString' => $e->getTraceAsString()]);
            return false;
        }
    }

    /**
     * Checks if the Zenkipay key is valid
     *
     * @return boolean
     */
    protected function validateZenkipayKey()
    {
        $result = $this->getAccessToken();
        if (array_key_exists('access_token', $result)) {
            return true;
        }

        return false;
    }

    /**
     * Get Zenkipay access token
     *
     * @return array
     */
    protected function getAccessToken()
    {
        $url = $this->base_url . '/public/v1/merchants/plugin/token';

        $ch = curl_init();
        $payload = $this->pk;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:text/plain']);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $result = curl_exec($ch);

        if ($result === false) {
            $this->logger->error('Curl error', ['curl_errno' => curl_errno($ch), 'curl_error' => curl_error($ch)]);
            return [];
        }

        curl_close($ch);

        return json_decode($result, true);
    }

    /**
     * Updates Zenkipay's merchantOrderId after WooCommerce register the order
     *
     * @param mixed $zenkipay_order_id
     * @param mixed $order_id
     *
     * @return boolean
     */
    protected function updateZenkipayOrder($zenkipay_order_id, $order_id)
    {
        try {
            $token_result = $this->getAccessToken();
            if (!array_key_exists('access_token', $token_result)) {
                $this->logger->error('Zenkipay - updateZenkipayOrder: Error al obtener access_token');
                return false;
            }

            $zenkipay_key = $this->pk;
            $payload = json_encode(['zenkipayKey' => $zenkipay_key, 'merchantOrderId' => $order_id]);
            $url = $this->base_url . '/v1/orders/' . $zenkipay_order_id;
            $headers = ['Accept: */*', 'Content-Type: application/json', 'Authorization: Bearer ' . $token_result['access_token']];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            $result = curl_exec($ch);

            if ($result === false) {
                $this->logger->error('Curl error ' . curl_errno($ch) . ': ' . curl_error($ch));
                return false;
            }

            curl_close($ch);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Zenkipay - updateZenkipayOrder: ', ['msg' => $e->getMessage(), 'traceAsString' => $e->getTraceAsString()]);
            return false;
        }
    }
}
