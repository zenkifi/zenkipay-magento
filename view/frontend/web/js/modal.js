/**
 * Zenki_Zenkipay Magento JS component
 *
 * @category    Payments
 * @package     Zenki_Zenkipay
 * @author      Federico Balderas
 * @copyright   Zenki (https://zenki.fi)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        // var self = this;
        var orderId = config.zenki_order_id;
        var paymentSignature = config.payment_signature;

        var purchaseOptions = {
            orderId,
            paymentSignature,
        };

        console.log('purchaseOptions', JSON.stringify(purchaseOptions, null, 2));

        zenkipay.openModal(purchaseOptions, function (error, data) {
            if (error) {
                // self.messageContainer.addErrorMessage({
                //     message: 'An unexpected error has occurred.',
                // });
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            return;
        });
    };
});
