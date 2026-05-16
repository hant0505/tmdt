define(
    [
        'jquery',
        'ko',
        'uiComponent'
    ],
    function ($, ko, Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Magento_Checkout/tekntek-payment-selection'
            },

            selectedMethod: ko.observable('vnpay'),
            showVnpayDetails: ko.observable(true),

            initialize: function () {
                this._super();
                var self = this;

                // Watch for changes in selected payment method
                this.selectedMethod.subscribe(function(newValue) {
                    if (newValue === 'vnpay') {
                        self.showVnpayDetails(true);
                    } else {
                        self.showVnpayDetails(false);
                    }
                });

                return this;
            },

            isVnpay: function() {
                return this.selectedMethod() === 'vnpay';
            },

            isCashOnDelivery: function() {
                return this.selectedMethod() === 'cashondelivery';
            },

            isCheckmo: function() {
                return this.selectedMethod() === 'checkmo';
            }
        });
    }
);
