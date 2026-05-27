/**
 * Payment Discount Modal Mixin
 * Handles coupon modal for checkout payment page
 * Uses unified coupon modal handler
 */
define([
    'jquery'
], function ($) {
    'use strict';

    return function (target) {
        target.initialize = function () {
            this._super();
            // No-op: use native collapsible inline behavior for payment coupon.
            return this;
        };

        return target;
    };
});
