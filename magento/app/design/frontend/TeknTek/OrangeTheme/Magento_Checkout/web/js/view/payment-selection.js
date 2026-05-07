define(
    [
        'jquery',
        'ko',
        'uiComponent',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/checkout-data'
    ],
    function ($, ko, Component, quote, selectPaymentMethodAction, checkoutData) {
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
                    self.showVnpayDetails(newValue === 'vnpay');
                    self.applySelectedMethod(newValue);
                });

                // Keep custom radio UI synced with the real Magento quote selection.
                quote.paymentMethod.subscribe(function (paymentMethod) {
                    var customCode = self.mapQuoteMethodToCustomOption(paymentMethod && paymentMethod.method);
                    if (customCode && self.selectedMethod() !== customCode) {
                        self.selectedMethod(customCode);
                    }

                    self.moveNativePlaceOrderToolbar();
                });

                // Initialize from quote if already selected (e.g. after refresh/back).
                var existingMethod = quote.paymentMethod() && quote.paymentMethod().method;
                var existingCustomCode = this.mapQuoteMethodToCustomOption(existingMethod);
                if (existingCustomCode) {
                    this.selectedMethod(existingCustomCode);
                    this.showVnpayDetails(existingCustomCode === 'vnpay');
                } else {
                    this.applySelectedMethod(this.selectedMethod());
                }

                this.moveNativePlaceOrderToolbar();

                return this;
            },

            mapQuoteMethodToCustomOption: function (methodCode) {
                var normalized = (methodCode || '').toLowerCase();

                if (!normalized) {
                    return null;
                }

                if (normalized.indexOf('vnpay') !== -1 || normalized.indexOf('vnpayment') !== -1) {
                    return 'vnpay';
                }

                if (normalized === 'cashondelivery' || normalized === 'cash_on_delivery' || normalized === 'cod') {
                    return 'cashondelivery';
                }

                return null;
            },

            resolveMagentoMethodCode: function (desiredCode) {
                var methods = (window.checkoutConfig && window.checkoutConfig.paymentMethods) || [];
                var desired = (desiredCode || '').toLowerCase();
                var i;
                var method;

                for (i = 0; i < methods.length; i++) {
                    method = (methods[i] && methods[i].method ? methods[i].method : '').toLowerCase();
                    if (method === desired) {
                        return methods[i].method;
                    }
                }

                if (desired === 'vnpay') {
                    for (i = 0; i < methods.length; i++) {
                        method = (methods[i] && methods[i].method ? methods[i].method : '').toLowerCase();
                        if (method.indexOf('vnpay') !== -1 || method.indexOf('vnpayment') !== -1) {
                            return methods[i].method;
                        }
                    }
                }

                if (desired === 'cashondelivery') {
                    for (i = 0; i < methods.length; i++) {
                        method = (methods[i] && methods[i].method ? methods[i].method : '').toLowerCase();
                        if (method === 'cashondelivery' || method === 'cash_on_delivery' || method === 'cod') {
                            return methods[i].method;
                        }
                    }
                }

                return desiredCode;
            },

            applySelectedMethod: function (desiredCode) {
                var methodCode = this.resolveMagentoMethodCode(desiredCode);
                var current = quote.paymentMethod();

                if (!methodCode) {
                    return;
                }

                if (current && current.method === methodCode) {
                    checkoutData.setSelectedPaymentMethod(methodCode);
                    this.syncPlaceOrderActionAllowed(methodCode);
                    return;
                }

                selectPaymentMethodAction({
                    method: methodCode
                });

                checkoutData.setSelectedPaymentMethod(methodCode);
                this.syncPlaceOrderActionAllowed(methodCode);
            },

            syncPlaceOrderActionAllowed: function (methodCode, attempt) {
                var normalized = (methodCode || '').toLowerCase();
                var $methodContainer;
                var methodVm;
                var self = this;

                attempt = attempt || 0;

                if (normalized !== 'cashondelivery') {
                    return;
                }

                $methodContainer = $('#payment .payment-method').has(
                    'input[type="radio"][name="payment[method]"][value="' + methodCode + '"]'
                ).first();

                if ($methodContainer.length) {
                    methodVm = ko.dataFor($methodContainer[0]);

                    if (methodVm && typeof methodVm.isPlaceOrderActionAllowed === 'function') {
                        methodVm.isPlaceOrderActionAllowed(true);
                    }

                    return;
                }

                if (attempt < 20) {
                    window.setTimeout(function () {
                        self.syncPlaceOrderActionAllowed(methodCode, attempt + 1);
                    }, 100);
                }
            },

            moveNativePlaceOrderToolbar: function (attempt) {
                var $target = $('[data-role="tekntek-place-order-target"]');
                var $toolbar;
                var selectedMethod = quote.paymentMethod() && quote.paymentMethod().method;
                var $methodContainer;
                var self = this;

                attempt = attempt || 0;

                if (!$target.length) {
                    return;
                }

                if (selectedMethod) {
                    $methodContainer = $('#payment .payment-method').has(
                        'input[type="radio"][name="payment[method]"][value="' + selectedMethod + '"]'
                    ).first();

                    if ($methodContainer.length) {
                        $toolbar = $methodContainer.find('.payment-method-content > .actions-toolbar').first();
                    }
                }

                if (!$toolbar || !$toolbar.length) {
                    $toolbar = $('#payment .payment-method._active .payment-method-content > .actions-toolbar').first();
                }

                if (!$toolbar.length) {
                    $toolbar = $('#payment .payment-method-content > .actions-toolbar').first();
                }

                if ($toolbar.length) {
                    if (!$toolbar.data('tekntekOriginalParent')) {
                        $toolbar.data('tekntekOriginalParent', $toolbar.parent());
                    }

                    $target.children('.actions-toolbar').not($toolbar).each(function () {
                        var $staleToolbar = $(this);
                        var $originalParent = $staleToolbar.data('tekntekOriginalParent');

                        if ($originalParent && $originalParent.length) {
                            $staleToolbar.appendTo($originalParent);
                        } else {
                            $staleToolbar.remove();
                        }
                    });

                    $toolbar.appendTo($target);

                    return;
                }

                if (attempt < 20) {
                    window.setTimeout(function () {
                        self.moveNativePlaceOrderToolbar(attempt + 1);
                    }, 150);
                }
            },

            isVnpay: function() {
                return this.selectedMethod() === 'vnpay';
            },

            isCashOnDelivery: function() {
                return this.selectedMethod() === 'cashondelivery';
            }
        });
    }
);
