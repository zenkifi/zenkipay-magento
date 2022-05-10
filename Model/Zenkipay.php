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
            ModuleListInterface $moduleList, 
            TimezoneInterface $localeDate, 
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
     * 
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount     
     * @throws \Magento\Framework\Validator\Exception
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount) {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        /** @var \Magento\Sales\Model\Order\Address $billing */
        $billing = $order->getBillingAddress();

        try {
            $customer_data = array(
                'requires_account' => false,
                'name' => $billing->getFirstname(),
                'last_name' => $billing->getLastname(),
                'phone_number' => $billing->getTelephone(),
                'email' => $order->getCustomerEmail()
            );
                                        
            $charge_request = array(
                'method' => 'store',
                'currency' => strtolower($order->getBaseCurrencyCode()),
                'amount' => $amount,
                'description' => sprintf('ORDER #%s, %s', $order->getIncrementId(), $order->getCustomerEmail()),
                'order_id' => $order->getIncrementId()
            );            
            
            $this->logger->debug('#order', array('$customer_data' => $customer_data));
            $this->logger->debug('#order', array('$charge_request' => $charge_request));                            
                        
            $payment->setTransactionId(666);                                

            // Actualiza el estado de la orden
            $state = \Magento\Sales\Model\Order::STATE_NEW;
            $order->setState($state)->setStatus($state);
            
            // Registra el ID de la transacción 
            $order->setExtOrderId(666);                        
            $order->save();              
        } catch (\Exception $e) {
            $this->debugData(['exception' => $e->getMessage()]);
            $this->_logger->error(__( $e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__($this->error($e)));
        }

        $payment->setSkipOrderProcessing(true);
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
        return $this->getMerchantInfo();
    }
        
    public function getMerchantInfo() {
       
    }           

    public function getCurrentCurrency() {        
        // return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
        return $this->_storeManager->getStore()->getCurrentCurrencyCode();
    }

}
