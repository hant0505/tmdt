define([
    'mage/url',
    'Magento_Checkout/js/view/payment/default'
], function (urlBuilder, Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payment_ZaloPay/payment/zalopay'
        },

        redirectAfterPlaceOrder: false,

        afterPlaceOrder: function () {
            window.location.replace(urlBuilder.build('zalopay/payment/redirect'));
        },

        getDescription: function () {
            return 'Thanh toán thử qua ZaloPay Sandbox. Không dùng tiền thật.';
        }
    });
});
