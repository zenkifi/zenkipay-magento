<?php
/**
 * @category    Payments
 * @package     Zenki_Zenkipay
 * @author      Federico Balderas
 * @copyright   Zenki (https://zenki.fi)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Zenki\Zenkipay\Block\Onepage;

use Zenki\Zenkipay\Model\Zenkipay;
use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * Success class
 *
 * Main contact form block
 */
class Success extends \Magento\Checkout\Block\Onepage\Success
{
    protected $orderItemsDetails;

    protected $purchase_data_version = 'v1.0.0';

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger_interface;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Sales\Model\Order $orderItemsDetails,
        \Psr\Log\LoggerInterface $logger_interface,
        ProductRepositoryInterface $productRepository,
        Zenkipay $payment,
        array $data = []
    ) {
        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);
        $this->orderItemsDetails = $orderItemsDetails;
        $this->payment = $payment;
        $this->logger = $logger_interface;
        $this->productRepository = $productRepository;
    }

    public function getTransactionHash()
    {
        $incrementId = $this->_checkoutSession->getLastRealOrder()->getIncrementId();
        $order = $this->orderItemsDetails->loadByIncrementId($incrementId);
        $zenkiOrderId = $order->getExtOrderId();

        $zenkipay = new \Zenkipay\Sdk($this->payment->getApiKey(), $this->payment->getSecretKey());
        $zenkipay_order = $zenkipay->orders()->find($zenkiOrderId);

        $this->logger->debug('getTransactionHash ====> ' . json_encode($zenkipay_order));

        return [
            'trx_hash' => $zenkipay_order->paymentInfo->cryptoPayment->transactionHash,
            'trx_explorer_url' => $zenkipay_order->paymentInfo->cryptoPayment->networkScanUrl,
        ];
    }
}
