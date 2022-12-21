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

    public function getPurchaseOptions()
    {
        $zenkipay = new \Zenkipay\Sdk($this->payment->getApiKey(), $this->payment->getSecretKey());

        $incrementId = $this->_checkoutSession->getLastRealOrder()->getIncrementId();
        $order = $this->orderItemsDetails->loadByIncrementId($incrementId);
        $items = [];
        $items_types = [];

        foreach ($order->getAllVisibleItems() as $item) {
            // Existe diferentes tipos de producto, sin embargo solo se consideran 2 para ser SERVICE.
            // Tipos: configurable, simple, grouped, virtual, downloadable, bundle.
            $product = $this->getProductById($item->getProductId());
            $item_type = $product->getTypeId() == 'virtual' || $product->getTypeId() == 'downloadable' ? 'WITHOUT_CARRIER' : 'WITH_CARRIER';
            array_push($items_types, $item_type);

            $items[] = [
                'externalId' => $item->getProductId(),
                'name' => $item->getName(),
                'quantity' => (int) $item->getQtyOrdered(),
                'unitPrice' => round($item->getPrice(), 2), // without taxes
                'type' => $item_type,
            ];
        }

        $totalItemsAmount = $order->getSubtotal();
        $shipmentAmount = $order->getShippingAmount();
        $subtotalAmount = $shipmentAmount + $totalItemsAmount;

        $purchase_data = [
            'version' => $this->purchase_data_version,
            'type' => $this->getOrderType($items_types),
            'orderId' => $order->getId(),
            'shopper' => [
                'email' => $order->getCustomerEmail(),
            ],
            'items' => $items,
            'breakdown' => [
                'currencyCodeIso3' => $order->getOrderCurrencyCode(),
                'totalItemsAmount' => round($totalItemsAmount, 2), // without taxes
                'shipmentAmount' => round($shipmentAmount, 2), // without taxes
                'subtotalAmount' => round($subtotalAmount, 2), // without taxes
                'taxesAmount' => round($order->getTaxAmount(), 2),
                'localTaxesAmount' => 0,
                'importCosts' => 0,
                'discountAmount' => abs(round($order->getDiscountAmount(), 2)),
                'grandtotalAmount' => round($order->getBaseGrandTotal(), 2),
            ],
        ];

        $this->logger->debug('purchase_data ====> ' . json_encode($purchase_data));
        $zenkipay_order = $zenkipay->orders()->create($purchase_data);
        $this->logger->debug('zenkipay_order ====> ' . json_encode($zenkipay_order));

        return [
            'zenki_order_id' => $zenkipay_order->zenkiOrderId,
            'payment_signature' => $zenkipay_order->paymentSignature,
        ];
    }

    /**
     * Retrieve product by id
     *
     * @param int $productId
     * @return \Magento\Catalog\Api\Data\ProductInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getProductById($productId)
    {
        return $this->productRepository->getById($productId);
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

    /**
     * Get service type
     *
     * @param array $items_types
     *
     * @return string
     */
    protected function getOrderType($items_types)
    {
        $needles = ['WITH_CARRIER', 'WITHOUT_CARRIER'];
        if (empty(array_diff($needles, $items_types))) {
            return 'MIXED';
        } elseif (in_array('WITH_CARRIER', $items_types)) {
            return 'WITH_CARRIER';
        } else {
            return 'WITHOUT_CARRIER';
        }
    }
}
