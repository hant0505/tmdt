define([], function () {
    'use strict';

    return function (Target) {
        return Target.extend({
            checkoutUrl: window.checkout && window.checkout.checkoutUrl ? window.checkout.checkoutUrl : '/checkout',

            /**
             * Return all cart items instead of slicing by maxItemsToDisplay.
             *
             * @returns {Array}
             */
            getCartItems: function () {
                return this.getCartParamUnsanitizedHtml('items') || [];
            }
        });
    };
});