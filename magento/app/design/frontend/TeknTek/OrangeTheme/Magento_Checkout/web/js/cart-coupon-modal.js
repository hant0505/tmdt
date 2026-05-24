/**
 * Shopping Cart Coupon Modal Handler
 * Uses unified coupon modal handler for consistent behavior with payment page
 */
define([
    'jquery',
    'Magento_Checkout/js/coupon-modal-unified'
], function ($, couponHandler) {
    'use strict';

    $(function () {
        couponHandler.initCartModal();
    });
});
