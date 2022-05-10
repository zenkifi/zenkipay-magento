<?php
/** 
 * @category    Payments
 * @package     Zenki_Zenkipay
 * @author      Federico Balderas
 * @copyright   Zenki (https://zenki.fi)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Zenki\Zenkipay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Zenki\Zenkipay\Model\Zenkipay as Config;

/**
 * Class MerchantInfo
 */
class MerchantInfo implements ObserverInterface
{

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @param Config $config
     * @param ManagerInterface $messageManager
     */
    public function __construct(Config $config, ManagerInterface $messageManager) {
        $this->config = $config;
        $this->messageManager = $messageManager;
    }

    /**     
     *
     * @param Observer $observer          
     */
    public function execute(Observer $observer) {
        return $this->config->validateSettings();
    }

}
