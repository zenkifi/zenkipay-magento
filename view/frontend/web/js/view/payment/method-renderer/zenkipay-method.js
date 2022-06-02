/**
 * Zenki_Zenkipay Magento JS component
 *
 * @category    Payments
 * @package     Zenki_Zenkipay
 * @author      Federico Balderas
 * @copyright   Zenki (https://zenki.fi)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/model/quote'        
    ],
    function (Component, $, quote) {
        'use strict';

        var zenkipayOrderId = '';
        var totals = null;
        var customerInfo = null;        

        return Component.extend({
            defaults: {
                template: 'Zenki_Zenkipay/payment/zenkipay-offline'
            },

            getCode: function () {
                return 'zenki_zenkipay';
            },

            isActive: function () {
                return true;
            },

            /**
             * Prepare and process payment information
             */
            preparePayment: function () {
                console.log('zenkiPay', zenkiPay);

                totals = quote.totals._latestValue;
                customerInfo = quote.billingAddress._latestValue;

                var publicKey = window.checkoutConfig.payment.zenkipay.public_key;
                var amount = totals.base_grand_total;
                var currency = totals.quote_currency_code;
                var country = typeof customerInfo.countryId !== 'undefined' && customerInfo.countryId.length !== 0 ? customerInfo.countryId : '';                

                var items = totals.items.map(item => ({
                    itemId: item.item_id,
                    productName: item.name,                    
                    quantity: item.qty,
                    price: item.price                    
                }));

                var zenkipayKey = publicKey;

                var purchaseData = {
                    amount,
                    country,
                    currency,
                    items
                };

                var purchaseOptions = {
                    style: {
                        shape: 'square',
                        theme: 'light',
                    },
                    zenkipayKey: zenkipayKey,
                    purchaseData,
                };

                console.log('#preparePayment', { purchaseOptions });

                zenkiPay.openModal(purchaseOptions, this.handleZenkipayEvents);
                // const orderId = '1234567890';
                // const details = {
                //     postMsgType: 'done',
                //     isComplete: true
                // };
                // this.handleZenkipayEvents(null, orderId, details);
            },
            handleZenkipayEvents: function (error, data, details) {
                console.log('handleZenkipayEvents', { error, data, details })

                if (!error && details.postMsgType === 'done') {
                    zenkipayOrderId = data;
                    this.placeOrder();
                }

                if (error && details.postMsgType === 'error') {
                    this.messageContainer.addErrorMessage({
                        message: 'Ha ocurrido un error inesperado.'
                    });
                }

                return;
            },
            /**
             * @override
             */
            getData: function () {
                return {
                    'method': "zenki_zenkipay",
                    'additional_data': {
                        'zenkipay_order_id': zenkipayOrderId
                    }
                };
            },
        });
    }
);