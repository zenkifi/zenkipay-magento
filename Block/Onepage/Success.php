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

/**
 * Success class
 *
 * Main contact form block
 */
class Success extends \Magento\Checkout\Block\Onepage\Success
{
    protected $orderItemsDetails;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Sales\Model\Order $orderItemsDetails,
        Zenkipay $payment,
        array $data = []
    ) {
        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);
        $this->orderItemsDetails = $orderItemsDetails;
        $this->payment = $payment;
    }

    public function getPurchaseOptions()
    {
        $incrementId = $this->_checkoutSession->getLastRealOrder()->getIncrementId();
        $order = $this->orderItemsDetails->loadByIncrementId($incrementId);
        $items = [];

        foreach ($order->getAllItems() as $item) {
            $items[] = [
                'itemId' => $item->getProductId(),
                'productName' => $item->getName(),
                'quantity' => (int) $item->getQtyOrdered(),
                'price' => round($item->getPrice(), 2), // without taxes
            ];
        }

        $totalItemsAmount = $order->getSubtotal();
        $shipmentAmount = $order->getShippingAmount();
        $subtotalAmount = $shipmentAmount + $totalItemsAmount;

        $purchase_data = [
            'shopperEmail' => $order->getCustomerEmail(),
            'items' => $items,
            'merchantOrderId' => $order->getId(),
            'purchaseSummary' => [
                'currency' => $order->getOrderCurrencyCode(),
                'totalItemsAmount' => round($totalItemsAmount, 2), // without taxes
                'shipmentAmount' => round($shipmentAmount, 2), // without taxes
                'subtotalAmount' => round($subtotalAmount, 2), // without taxes
                'taxesAmount' => round($order->getTaxAmount(), 2),
                'discountAmount' => round($order->getDiscountAmount(), 2),
                'grandTotalAmount' => round($order->getBaseGrandTotal(), 2),
                'localTaxesAmount' => 0,
                'importCosts' => 0,
            ],
        ];

        $payload = json_encode($purchase_data);

        return [
            'purchase_data' => $payload,
            'public_key' => $this->payment->getPublicKey(),
            'signature' => $this->generateSignature($payload),
        ];
    }

    /**
     * Generates payload signature using the RSA private key
     *
     * @param string $payload Purchase data
     *
     * @return string
     */
    protected function generateSignature($payload)
    {
        $rsa_private_key = openssl_pkey_get_private($this->payment->getRsaPrivateKey());
        openssl_sign($payload, $signature, $rsa_private_key, 'RSA-SHA256');
        return base64_encode($signature);
    }
}
