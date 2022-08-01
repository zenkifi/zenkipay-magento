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
        var self = this;
        var zenkipayKey = config.public_key;
        var purchaseData = JSON.stringify(config.purchase_data);
        var purchaseSignature = config.signature;

        var purchaseOptions = {
            style: {
                shape: 'square',
                theme: 'light',
            },
            zenkipayKey,
            purchaseData,
            purchaseSignature,
        };

        zenkiPay.openModal(purchaseOptions, function (error, data, details) {
            if (!error && details.postMsgType === 'done') {
                console.log('DONE!');
                return;
            }

            if (error && details.postMsgType === 'error') {
                // self.messageContainer.addErrorMessage({
                //     message: 'An unexpected error has occurred.',
                // });
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            return;
        });
    };
});
