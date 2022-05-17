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
    protected $sk;    
    protected $sandbox_sk;    
    protected $live_sk;        
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
            array $data = array()            
    ) {
        
        parent::__construct(
            $context,
            $registry, 
            $extensionFactory,
            $customAttributeFactory,
            $paymentData, 
            $scopeConfig,
            $logger,
            null,
            null,            
            $data            
        );
                        
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
        $this->pk = $this->is_sandbox ? $this->sandbox_pk : $this->live_pk;                                
    }    

    /**
     * Assign corresponding data
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data) {
        parent::assignData($data);
                
        $infoInstance = $this->getInfoInstance();
        $additionalData = ($data->getData('additional_data') != null) ? $data->getData('additional_data') : $data->getData();
        
        $infoInstance->setAdditionalInformation('zenkipay_order_id', 
            isset($additionalData['zenkipay_order_id']) ? $additionalData['zenkipay_order_id'] :  null
        );
        
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
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount) {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();   
         /** @var \Magento\Sales\Model\Order\Address $billing */
        $billing = $order->getBillingAddress();

        $this->logger->debug('#capture', array('$order_id' => $order->getIncrementId(), '$trx_id' => $payment->getLastTransId(), '$status' => $order->getStatus(), '$amount' => $amount));                    
        
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
        } catch (\Exception $e) {
            $this->debugData(['exception' => $e->getMessage()]);
            $this->_logger->error(__( $e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
        
        return $this;
    }    
       
    public function getBaseUrlStore(){
        return $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);        
    }
    
    public function isLoggedIn() {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {
        return parent::isAvailable($quote);
    }

   
    public function getPublicKey() {
        return $this->pk;
    }

    /**
     * @return boolean
     */
    public function isSandbox() {
        return $this->is_sandbox;
    }

    public function getCode() {
        return $this->_code;
    }

    public function validateSettings() {           
        $response = $this->getMerchantInfo();
        $array = json_decode(json_encode($response), true);
        $this->logger->debug('#getMerchantInfo', ['response' => $array]);
        if (!isset($array['access_token'])) {
            throw new \Magento\Framework\Validator\Exception(__('Something went wrong while saving this configuration, your Zenkipay key is incorrect.'));
        }
        
        return; 
    }
        
    public function getMerchantInfo() {
        $url = "https://dev-gateway.zenki.fi/public/v1/merchants/plugin/token";
       
        $ch = curl_init();
        $payload = $this->pk;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:text/plain'));

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $result = curl_exec($ch);
        $response = null;
        if ($result === false) {
            $this->logger->error("Curl error", array("curl_errno" => curl_errno($ch), "curl_error" => curl_error($ch)));
        } else {
            $info = curl_getinfo($ch);
            $response = json_decode($result);
            $response->http_code = $info['http_code'];
            $this->logger->debug("request", array("HTTP code " => $info['http_code'], "on request to" => $info['url']));
        }
    
        curl_close($ch);
        $this->logger->debug('#request response', [json_encode($response)]);
        return $response;
    }           

    public function getCurrentCurrency() {        
        // return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
        return $this->_storeManager->getStore()->getCurrentCurrencyCode();
    }

}
