define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-billing-address'
], function (quote, selectBillingAddress) {
    'use strict';

    function isCashOnDelivery(component) {
        return (component && component.getCode ? component.getCode() : '').toLowerCase() === 'cashondelivery';
    }

    function syncBillingAddressFromShipping() {
        var shippingAddress = quote.shippingAddress();

        if (shippingAddress) {
            selectBillingAddress(shippingAddress);
        }
    }

    return function (Target) {
        return Target.extend({
            initialize: function () {
                this._super();

                if (!isCashOnDelivery(this)) {
                    return this;
                }

                if (quote.paymentMethod() && quote.paymentMethod().method === this.getCode()) {
                    syncBillingAddressFromShipping();
                }

                quote.paymentMethod.subscribe(function (paymentMethod) {
                    if (paymentMethod && paymentMethod.method === this.getCode()) {
                        syncBillingAddressFromShipping();
                    }
                }, this);

                quote.shippingAddress.subscribe(function () {
                    if (quote.paymentMethod() && quote.paymentMethod().method === this.getCode()) {
                        syncBillingAddressFromShipping();
                    }
                }, this);

                return this;
            }
        });
    };
});