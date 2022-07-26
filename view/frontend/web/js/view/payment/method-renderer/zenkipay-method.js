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
    });
});
