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
use Zenki\Zenkipay\Model\SyncAccount;

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
    protected $sync_code;
    protected $api_key = '';
    protected $secret_key = '';
    protected $webhook_signing_secret = '';
    protected $scopeConfig;
    protected $logger;
    protected $_storeManager;
    protected $customerSession;
    protected $configWriter;
    protected $zenkipayCredentialsFactory;

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
        \Zenki\Zenkipay\Model\CredentialsFactory $zenkipayCredentialsFactory,
        array $data = []
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, null, null, $data);

        $this->zenkipayCredentialsFactory = $zenkipayCredentialsFactory;
        $this->customerSession = $customerSession;

        $this->_storeManager = $storeManager;
        $this->logger = $logger_interface;

        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->country_factory = $countryFactory;

        $this->is_active = $this->getConfigData('active');
        $this->is_sandbox = $this->getConfigData('is_sandbox');
        $this->sync_code = $this->getConfigData('sync_code');

        if (!empty($this->sync_code)) {
            $this->setCredentials();
        }
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

        $infoInstance->setAdditionalInformation('zenki_order_id', isset($additionalData['zenki_order_id']) ? $additionalData['zenki_order_id'] : null);
        $infoInstance->setAdditionalInformation('trx_hash', isset($additionalData['trx_hash']) ? $additionalData['trx_hash'] : null);
        $infoInstance->setAdditionalInformation('trx_explorer_url', isset($additionalData['trx_explorer_url']) ? $additionalData['trx_explorer_url'] : null);

        return $this;
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
        $zenkiOrderId = $this->getInfoInstance()->getAdditionalInformation('zenki_order_id');

        try {
            // Actualiza el estado de la orden
            $state = \Magento\Sales\Model\Order::STATE_NEW;
            $order->setState($state)->setStatus($state);
            $order->setExtOrderId($zenkiOrderId);
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
        return rtrim($this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB), '/');
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
            $this->deleteCredentials();
            throw new \Magento\Framework\Validator\Exception(__('An error occurred while syncing the account.'));
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
        $this->logger->info('Zenkipay - api_key => ' . $this->api_key);
        $this->logger->info('Zenkipay - secret_key => ' . $this->secret_key);
        $this->logger->info('Zenkipay - sync_code => ' . $this->sync_code);

        $credentials = $this->getCredentials($this->sync_code);
        $this->logger->info('Zenkipay - validateZenkipayKey - $credentials => ' . json_encode($credentials));
        if ($credentials == false) {
            return false;
        }

        $zenkipay = new \Zenkipay\Sdk($credentials['api_key'], $credentials['secret_key']);
        $result = $zenkipay->getAccessToken();

        $this->logger->info('Zenkipay - getAccessToken => ' . $result);

        // Se valida que la respuesta sea un JWT
        $regex = preg_match('/^([a-zA-Z0-9_=]+)\.([a-zA-Z0-9_=]+)\.([a-zA-Z0-9_\-\+\/=]*)/', $result);
        if ($regex != 1) {
            return false;
        }

        return true;
    }

    private function getCredentials($sync_code)
    {
        $urlStore = $this->getBaseUrlStore();
        $formmattedCode = trim(str_replace('-', '', $sync_code));

        try {
            $syncedAccount = $this->getSyncedAccount();
            $this->logger->info('Zenkipay - syncedAccount => ' . json_encode($syncedAccount));

            $oldSyncCode = !$syncedAccount ? '' : $syncedAccount['sync_code'];
            $this->logger->info('Zenkipay - formmattedCode => ' . $formmattedCode);
            $this->logger->info('Zenkipay - oldSyncCode => ' . $oldSyncCode);

            if (!$syncedAccount || $oldSyncCode != $formmattedCode) {
                $syncAccount = new SyncAccount();
                $credentials = $syncAccount->sync($formmattedCode, $urlStore);

                $this->logger->info('Zenkipay - credentials => ' . json_encode($credentials));

                if (isset($credentials['errorCode'])) {
                    throw new \Magento\Framework\Validator\Exception(__($credentials['humanMessage']));
                }

                // Se valida que la sincronización haya sido exitosa
                if ($credentials['status'] !== 'SYNCHRONIZED') {
                    throw new \Magento\Framework\Validator\Exception(__('An unexpected error occurred synchronizing the account. Status ' . $credentials['status'] . '.'));
                }

                $this->deleteCredentials();

                // Se guarda en BD la relación
                $dev_credentials = [
                    'sync_code' => $formmattedCode,
                    'api_key' => $credentials['synchronizationAccessData']['sandboxApiAccessData']['apiAccessData']['apiKey'],
                    'secret_key' => $credentials['synchronizationAccessData']['sandboxApiAccessData']['apiAccessData']['secretKey'],
                    'whsec' => $credentials['synchronizationAccessData']['sandboxApiAccessData']['webhookAccessData']['signingSecret'],
                    'env' => 'DEV',
                ];
                $dev_cred_factory = $this->zenkipayCredentialsFactory->create();
                $dev_cred_factory->addData($dev_credentials)->save();

                // Se guarda en BD la relación
                $prod_credentials = [
                    'sync_code' => $formmattedCode,
                    'api_key' => $credentials['synchronizationAccessData']['liveApiAccessData']['apiAccessData']['apiKey'],
                    'secret_key' => $credentials['synchronizationAccessData']['liveApiAccessData']['apiAccessData']['secretKey'],
                    'whsec' => $credentials['synchronizationAccessData']['liveApiAccessData']['webhookAccessData']['signingSecret'],
                    'env' => 'PROD',
                ];
                $prod_cred_factory = $this->zenkipayCredentialsFactory->create();
                $prod_cred_factory->addData($prod_credentials)->save();

                return $this->is_sandbox ? $dev_credentials : $prod_credentials;
            }

            return $syncedAccount;
        } catch (\Exception $e) {
            $this->logger->error('Zenkipay - getCredentials: ' . $e->getMessage());
            return [];
        }
    }

    private function getSyncedAccount()
    {
        $env = $this->is_sandbox ? 'DEV' : 'PROD';
        try {
            $znk_cred = $this->zenkipayCredentialsFactory->create();
            return $znk_cred->fetchOneBy('env', $env);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
    }

    private function deleteCredentials()
    {
        $this->logger->info('Zenkipay - deleteCredentials');
        try {
            $znk_cred = $this->zenkipayCredentialsFactory->create();
            return $znk_cred->deleteAll();
        } catch (\Exception $e) {
            $this->logger->error('Zenkipay - handleTrackingNumber: ' . $e->getMessage());
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
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

    private function setCredentials()
    {
        $credentials = $this->getCredentials($this->sync_code);
        $this->logger->info('Zenkipay - setCredentials => ' . json_encode($credentials));

        if (!count($credentials)) {
            return;
        }

        $this->api_key = $credentials['api_key'];
        $this->secret_key = $credentials['secret_key'];
        $this->webhook_signing_secret = $credentials['whsec'];
    }

    public function updateZenkiOrder($zenki_order_id, $data)
    {
        try {
            $this->logger->info('Zenkipay - updateZenkiOrder => ' . json_encode($data));

            $zenkipay = new \Zenkipay\Sdk($this->api_key, $this->secret_key);
            $zenkipay->orders()->update($zenki_order_id, $data);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Zenkipay - updateZenkiOrder: ' . $e->getMessage());
            return false;
        }
    }
}
