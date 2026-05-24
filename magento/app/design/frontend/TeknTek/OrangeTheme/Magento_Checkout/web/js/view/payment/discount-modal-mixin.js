/**
 * Payment Discount Modal Mixin
 * Handles coupon modal for checkout payment page
 * Uses unified coupon modal handler
 */
define([
    'jquery',
    'Magento_Checkout/js/coupon-modal-unified'
], function ($, couponHandler) {
    'use strict';

    return function (target) {
        target.initialize = function () {
            this._super();
            couponHandler.initPaymentModal();
            return this;
        };

        return target;
    };
});
