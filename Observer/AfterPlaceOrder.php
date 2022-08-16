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
use Zenki\Zenkipay\Model\Zenkipay as Config;

class AfterPlaceOrder implements ObserverInterface
{
    protected $config;
    protected $order;
    protected $logger;
    protected $_actionFlag;
    protected $_response;
    protected $_redirect;

    public function __construct(
        Config $config,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\App\Response\RedirectInterface $redirect,
        \Magento\Framework\App\ActionFlag $actionFlag,
        \Psr\Log\LoggerInterface $logger_interface,
        \Magento\Framework\App\ResponseInterface $response
    ) {
        $this->config = $config;
        $this->order = $order;
        $this->logger = $logger_interface;

        $this->_redirect = $redirect;
        $this->_response = $response;

        $this->_actionFlag = $actionFlag;
    }

    public function execute(Observer $observer)
    {
        $orderId = $observer->getEvent()->getOrderIds();
        $order = $this->order->load($orderId[0]);

        if ($order->getPayment()->getMethod() == 'zenki_zenkipay') {
            $this->logger->debug('#AfterPlaceOrder zenki_zenkipay', ['order_id' => $orderId[0], 'order_status' => $order->getStatus(), 'ext_order_id' => $order->getExtOrderId()]);
        }

        return $this;
    }
}
