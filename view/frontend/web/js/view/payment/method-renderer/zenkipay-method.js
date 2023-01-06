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

    var previousMsgType = '';

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
            var createOrderUrl = window.checkoutConfig.payment.zenkipay.create_order_url;

            console.log('createOrderUrl', createOrderUrl);

            $.post(createOrderUrl, {}).success((response) => {
                console.log('response', response);

                if (!response.hasOwnProperty('error')) {
                    var purchaseOptions = {
                        paymentSignature: response.payment_signature,
                        orderId: response.zenki_order_id,
                    };

                    zenkipay.openModal(purchaseOptions, function (error, data) {
                        console.log('handleZenkipayEvents error => ', error);
                        console.log('handleZenkipayEvents data => ', data);
                        console.log('handleZenkipayEvents previousMsgType => ', previousMsgType);

                        if (error) {
                            self.messageContainer.addErrorMessage({
                                message: error,
                            });
                            zenkipay.closeModal();
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                            return;
                        }

                        if (data.postMsgType === 'shopper_payment_confirmation') {
                            self.placeOrder();
                            return;
                        }

                        if (data.postMsgType === 'done') {
                            self.placeOrder();
                            return;
                        }

                        if ((previousMsgType === 'processing_payment' || previousMsgType === 'done') && data.isCompleted) {
                            self.placeOrder();
                        }

                        previousMsgType = data.postMsgType;
                        return;
                    });
                } else {
                    console.log('preparePayment error', response.message);
                    self.messageContainer.addErrorMessage({
                        message: response.message,
                    });
                }
            });
        },
    });
});
