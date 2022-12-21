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
                $config['payment']['zenkipay']['create_order_url'] = $this->payment->getBaseUrlStore() . 'zenkipay/payment/order';
            }
        }

        return $config;
    }
}
