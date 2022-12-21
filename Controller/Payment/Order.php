<?php
/**
 * @category    Payments
 * @package     Zenki_Zenkipay
 * @author      Federico Balderas
 * @copyright   Zenki (https://zenki.fi)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Zenki\Zenkipay\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Checkout\Model\Cart;
use Zenki\Zenkipay\Model\Zenkipay;

class Order extends \Magento\Framework\App\Action\Action
{
    protected $purchase_data_version = 'v1.0.0';
    protected $payment;
    protected $logger;
    protected $cart;

    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    protected $quoteManagement;

    /**
     *
     * @param Context $context
     * @param Zenkipay $payment
     * @param \Psr\Log\LoggerInterface $logger_interface
     */
    public function __construct(Context $context, Zenkipay $payment, \Psr\Log\LoggerInterface $logger_interface, Cart $cart, \Magento\Quote\Model\QuoteManagement $quoteManagement)
    {
        parent::__construct($context);
        $this->payment = $payment;
        $this->logger = $logger_interface;
        $this->cart = $cart;
        $this->quoteManagement = $quoteManagement;
    }
    public function execute()
    {
        $data = null;
        $post = $this->getRequest()->getPostValue();

        try {
            $quote = $this->cart->getQuote();
            // $this->logger->debug('#CardBin', ['cardInfo' => $post['card_bin']]);
            $zenkipay = new \Zenkipay\Sdk($this->payment->getApiKey(), $this->payment->getSecretKey());

            $purchase_data = $this->getPurchaseData();

            $this->logger->debug('purchase_data ====> ' . json_encode($purchase_data));
            $zenkipay_order = $zenkipay->orders()->create($purchase_data);
            $this->logger->debug('zenkipay_order ====> ' . json_encode($zenkipay_order));

            // Create Order From Quote
            // $order = $this->quoteManagement->submit($quote);

            $data = [
                // 'real_order_id' => $order->getRealOrderId(),
                // 'increment_id' => $order->getIncrementId(),
                'zenki_order_id' => $zenkipay_order->zenkiOrderId,
                'payment_signature' => $zenkipay_order->paymentSignature,
            ];
        } catch (\Exception $e) {
            $this->logger->error('#order', ['msg' => $e->getMessage()]);
            $data = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($data);
        return $resultJson;
    }

    public function getPurchaseData()
    {
        $items = [];
        $items_types = [];
        $quote = $this->cart->getQuote();
        $billing_address = $quote->getBillingAddress();

        foreach ($quote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            $item_type = $product->getTypeId() == 'virtual' || $product->getTypeId() == 'downloadable' ? 'WITHOUT_CARRIER' : 'WITH_CARRIER';
            array_push($items_types, $item_type);

            $items[] = [
                'externalId' => $item->getProductId(),
                'name' => $item->getName(),
                'quantity' => $item->getQty(),
                'unitPrice' => round($item->getPrice(), 2), // without taxes
                'type' => $item_type,
            ];
        }

        $totalItemsAmount = $quote->getSubtotal();
        $shipmentAmount = $quote->getShippingAddress()->getShippingAmount();
        $subtotalAmount = $shipmentAmount + $totalItemsAmount;
        $discount = $totalItemsAmount - $quote->getSubtotalWithDiscount();

        $purchase_data = [
            'version' => $this->purchase_data_version,
            'type' => $this->getOrderType($items_types),
            'cartId' => $quote->getId(),
            'shopper' => [
                'email' => $quote->getCustomerEmail(),
            ],
            'countryCodeIso2' => $billing_address->getCountryId(),
            'items' => $items,
            'breakdown' => [
                'currencyCodeIso3' => $quote->getQuoteCurrencyCode(),
                'totalItemsAmount' => round($totalItemsAmount, 2), // without taxes
                'shipmentAmount' => round($shipmentAmount, 2), // without taxes
                'subtotalAmount' => round($subtotalAmount, 2), // without taxes
                'taxesAmount' => round($quote->getTotals()['tax']->getValue(), 2),
                'localTaxesAmount' => 0,
                'importCosts' => 0,
                'discountAmount' => abs(round($discount, 2)),
                'grandTotalAmount' => round($quote->getBaseGrandTotal(), 2),
            ],
        ];

        return $purchase_data;
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
