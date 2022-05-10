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

        var totals = null;
        var customerData = null;
        var callbackUrl = window.checkoutConfig.payment.zenkipay.cb_url;
        // var cancelUrl = window.checkoutConfig.payment.zenkipay.cancel_url;      

        $(function () {
            console.log("ready!");
            console.log('OpenPay', OpenPay);
            console.log('Stripe', Stripe);
            console.log('zenkiPay', zenkiPay);
        });

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
                console.log('OpenPay2', OpenPay);
                console.log('Stripe2', Stripe);
                console.log('zenkiPay2', window.zenkiPay);

                totals = quote.totals._latestValue;
                customerData = quote.billingAddress._latestValue;

                var publicKey = window.checkoutConfig.payment.zenkipay.public_key;
                var amount = totals.grand_total;
                var currency = totals.quote_currency_code;
                var country = typeof customerData.countryId !== 'undefined' && customerData.countryId.length !== 0 ? customerData.countryId : '';
                var items = totals.items.map(item => ({
                    itemId: item.item_id,
                    name: item.name,
                    quantity: item.qty,
                    price: item.price,
                    thumbnailUrl: ''
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
                // self.placeOrder();
                // return;


                // self.messageContainer.addErrorMessage({
                //     message: response.data.description
                // });
            },

            handleZenkipayEvents: function (error, data, details) {
                this.messageContainer.clear();
                console.log('handleZenkipayEvents', { error, data, details })
                // var storeOrderId = "1";

                // const events = {
                //     'done': (data) => {
                //         data.complete = '1';
                //         this.sendPaymentRequestResponse(data);
                //     },
                //     'cancel': (data) => {
                //         // setTimeout(this.redirectTo, 1000, cancelUrl);
                //         this.messageContainer.addErrorMessage({
                //             message: response.data.description
                //         });
                //     }
                // };

                // const dataRequest = {
                //     order_id: storeOrderId,
                //     complete: ''
                // };

                // if (error && error.postMsgType && error.postMsgType === 'error') {
                //     dataRequest.complete = '0'
                //     this.sendPaymentRequestResponse(dataRequest);
                // } else if (details && details.postMsgType && events[details.postMsgType]) {
                //     events[details.postMsgType](dataRequest);
                // }
            },

            sendPaymentRequestResponse: function (data) {
                $.post(callbackUrl, data).success((result) => {
                    const response = JSON.parse(result);
                    console.log('sendPaymentRequestResponse', response);
                    // const redirectUrl = response.redirect_url;
                    // setTimeout(this.redirectTo, 1000, redirectUrl);

                    this.placeOrder();
                    return;
                });
            },

            redirectTo: function (url) {
                location.href = url;
            }
        });
    }
);