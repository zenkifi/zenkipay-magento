<?php
/**
 * Copyright © 2015 Pay.nl All rights reserved.
 */

namespace Zenki\Zenkipay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Zenki\Zenkipay\Model\Zenkipay;
use Magento\Checkout\Model\Cart;

class ZenkipayConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCodes = ['zenki_zenkipay'];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @var \Zenki\Zenkipay\Model\Zenkipay
     */
    protected $payment;

    protected $cart;

    protected $currentCurrency;

    /**
     * @param PaymentHelper $paymentHelper
     * @param Zenkipay $payment
     */
    public function __construct(PaymentHelper $paymentHelper, Zenkipay $payment, Cart $cart)
    {
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
        $this->cart = $cart;
        $this->payment = $payment;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $items = [];
                $billing_address = $this->cart->getQuote()->getBillingAddress();

                foreach ($this->cart->getQuote()->getAllItems() as $item) {
                    $items[] = [
                        'itemId' => $item->getProductId(),
                        'productName' => $item->getName(),
                        'quantity' => $item->getQty(),
                        'price' => round($item->getPrice(), 2),
                    ];
                }

                $purchase_data = [
                    'amount' => round($this->cart->getQuote()->getBaseGrandTotal(), 2),
                    'country' => $billing_address->getCountryId(),
                    'currency' => $this->cart->getQuote()->getQuoteCurrencyCode(),
                    'items' => $items,
                    'shopperCarId' => $this->cart->getQuote()->getId(),
                ];

                $payload = json_encode($purchase_data);

                $config['payment']['zenkipay']['purchase_data'] = $payload;
                $config['payment']['zenkipay']['public_key'] = $this->payment->getPublicKey();
                $config['payment']['zenkipay']['signature'] = $this->generateSignature($payload);
            }
        }

        return $config;
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
