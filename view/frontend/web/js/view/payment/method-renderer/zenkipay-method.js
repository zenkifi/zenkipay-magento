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
define(['Magento_Checkout/js/view/payment/default', 'jquery', 'Magento_Checkout/js/model/quote'], function (Component, $, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Zenki_Zenkipay/payment/zenkipay-offline',
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
            var self = this;
            var zenkipayKey = window.checkoutConfig.payment.zenkipay.public_key;
            var purchaseData = window.checkoutConfig.payment.zenkipay.purchase_data;
            var zenkipaySignature = window.checkoutConfig.payment.zenkipay.signature;

            var purchaseOptions = {
                style: {
                    shape: 'square',
                    theme: 'light',
                },
                zenkipayKey,
                purchaseData,
                zenkipaySignature,
            };

            zenkiPay.openModal(purchaseOptions, function (error, data, details) {
                if (!error && details.postMsgType === 'done') {
                    var zenkipayOrderId = data.orderId;
                    $('#zenkipay_order_id').val(zenkipayOrderId);
                    self.placeOrder();
                }

                if (error && details.postMsgType === 'error') {
                    self.messageContainer.addErrorMessage({
                        message: 'An unexpected error has occurred.',
                    });
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }

                return;
            });
        },
        /**
         * @override
         */
        getData: function () {
            return {
                method: 'zenki_zenkipay',
                additional_data: {
                    zenkipay_order_id: $('#zenkipay_order_id').val(),
                },
            };
        },
    });
});
