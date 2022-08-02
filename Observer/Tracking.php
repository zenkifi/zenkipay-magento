<?php
/**
 * @category    Payments
 * @package     Zenki_Zenkipay
 * @author      Federico Balderas
 * @copyright   Zenki (https://zenki.fi)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Zenki\Zenkipay\Observer;

use Exception;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Zenki\Zenkipay\Model\Zenkipay;
use Order\Shipment\Track;

/**
 * Class Tracking
 *
 * @package Mollie\Payment\Observer
 */
class Tracking implements ObserverInterface
{
    /**
     * @var Zenkipay
     */
    private $zenkipay;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Tracking constructor.
     *
     * @param Zenkipay  $zenkipay
     */
    public function __construct(\Psr\Log\LoggerInterface $logger, Zenkipay $zenkipay)
    {
        $this->zenkipay = $zenkipay;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var \Magento\Sales\Model\Order\Shipment\Track $track */
            $track = $observer->getEvent()->getTrack();
            // 'carrier' => ,

            /** @var \Magento\Sales\Model\Order\Shipment $shipment */
            $shipment = $track->getShipment();

            /** @var \Magento\Sales\Model\Order $order */
            $order = $shipment->getOrder();

            if (empty($order->getExtOrderId())) {
                $this->logger->error('Zenkipay - Tracking: No ExtOrderId exists. Order ID:' . $order->getId());
                return;
            }

            $this->logger->info('Tracking - Carrier: ' . $track->getTitle() . ', code: ' . $track->getTrackNumber());

            $data = [['orderId' => $order->getExtOrderId(), 'merchantOrderId' => $order->getId(), 'trackingId' => $track->getTrackNumber()]];

            $this->zenkipay->handleTrackingNumber($data);
        } catch (Exception $e) {
            // otra manera de llamar a error_log():
            $this->logger->error('Tracking: ' . $e->getMessage());
        }
    }
}
