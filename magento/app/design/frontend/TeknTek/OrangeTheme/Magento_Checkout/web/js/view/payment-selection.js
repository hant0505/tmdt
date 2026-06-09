define(
    [
        'jquery',
        'ko',
        'uiComponent',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment-service',
        'Magento_Checkout/js/model/payment/method-list',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/payment/additional-validators',
        'uiRegistry',
        'mage/url'
    ],
    function ($, ko, Component, quote, selectPaymentMethodAction, checkoutData, paymentService, methodList, placeOrderAction, additionalValidators, registry, urlBuilder) {
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
                var debugEnabled = !!window.tekntekPaymentDebug;
                var logDebug = function (message, payload) {
                    if (!debugEnabled) {
                        return;
                    }

                    if (payload !== undefined) {
                        console.log('[TeknTek][Payment]', message, payload);
                        return;
                    }

                    console.log('[TeknTek][Payment]', message);
                };

                this.canPlaceOrder = ko.pureComputed(function () {
                    return self.isAvailableMethodCode(self.resolveMagentoMethodCode(self.selectedMethod()));
                });

                this.isCashOnDeliveryAvailable = ko.pureComputed(function () {
                    var code = self.resolveMagentoMethodCode('cashondelivery');
                    return self.isAvailableMethodCode(code);
                });

                this.isVnpayAvailable = ko.pureComputed(function () {
                    var code = self.resolveMagentoMethodCode('vnpay');
                    return self.isAvailableMethodCode(code);
                });

                this.isZaloPayAvailable = ko.pureComputed(function () {
                    var code = self.resolveMagentoMethodCode('zalopay');
                    return self.isAvailableMethodCode(code);
                });

                // Get reference to payments-list component asynchronously (it initializes after this component)
                this.paymentsList = ko.observable(null);
                registry.async('checkout.steps.billing-step.payment.payments-list')(function(component) {
                    self.paymentsList(component);
                    if (!!window.tekntekPaymentDebug) {
                        console.log('[TeknTek][Payment] payments-list loaded:', component);
                    }
                });

                // Watch for changes in selected payment method
                this.selectedMethod.subscribe(function(newValue) {
                    self.showVnpayDetails(newValue === 'vnpay');
                    self.applySelectedMethod(newValue);
                });

                methodList.subscribe(function () {
                    logDebug('Available methods updated', self.getMethodList());
                    self.ensureSelectedMethodAvailable();
                });

                // Keep custom radio UI synced with the real Magento quote selection.
                quote.paymentMethod.subscribe(function (paymentMethod) {
                    var customCode = self.mapQuoteMethodToCustomOption(paymentMethod && paymentMethod.method);
                    if (customCode && self.selectedMethod() !== customCode) {
                        self.selectedMethod(customCode);
                    }

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

                this.ensureSelectedMethodAvailable();

                logDebug('Initial available methods', this.getMethodList());

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

                if (normalized.indexOf('zalopay') !== -1 || normalized.indexOf('zalo_pay') !== -1) {
                    return 'zalopay';
                }

                if (normalized === 'cashondelivery' || normalized === 'cash_on_delivery' || normalized === 'cod') {
                    return 'cashondelivery';
                }

                return null;
            },

            resolveMagentoMethodCode: function (desiredCode) {
                var methods = this.getMethodList();
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

                if (desired === 'zalopay') {
                    for (i = 0; i < methods.length; i++) {
                        method = (methods[i] && methods[i].method ? methods[i].method : '').toLowerCase();
                        if (method.indexOf('zalopay') !== -1 || method.indexOf('zalo_pay') !== -1) {
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

            getMethodList: function () {
                var methods = paymentService.getAvailablePaymentMethods();

                if (methods && methods.length) {
                    return methods;
                }

                methods = (window.checkoutConfig && window.checkoutConfig.paymentMethods) || [];

                return methods;
            },

            isAvailableMethodCode: function (methodCode) {
                var methods = this.getMethodList();
                var target = (methodCode || '').toLowerCase();

                return methods.some(function (method) {
                    return (method && method.method ? method.method : '').toLowerCase() === target;
                });
            },

            ensureSelectedMethodAvailable: function () {
                var desiredCustom = this.selectedMethod && this.selectedMethod();
                var desiredCode = this.resolveMagentoMethodCode(desiredCustom);
                var methods = this.getMethodList();
                var fallback;
                var fallbackCustom;

                if (desiredCode && this.isAvailableMethodCode(desiredCode)) {
                    return;
                }

                if (!methods.length) {
                    return;
                }

                fallback = methods[0].method;
                fallbackCustom = this.mapQuoteMethodToCustomOption(fallback) || fallback;

                if (fallbackCustom && this.selectedMethod() !== fallbackCustom) {
                    this.selectedMethod(fallbackCustom);
                }

                if (fallback) {
                    selectPaymentMethodAction({
                        method: fallback
                    });
                    checkoutData.setSelectedPaymentMethod(fallback);
                }
            },

            applySelectedMethod: function (desiredCode) {
                var methodCode = this.resolveMagentoMethodCode(desiredCode);
                var current = quote.paymentMethod();

                if (!methodCode) {
                    return;
                }

                if (!this.isAvailableMethodCode(methodCode)) {
                    this.ensureSelectedMethodAvailable();
                    return;
                }

                if (current && current.method === methodCode) {
                    checkoutData.setSelectedPaymentMethod(methodCode);
                    return;
                }

                selectPaymentMethodAction({
                    method: methodCode
                });

                checkoutData.setSelectedPaymentMethod(methodCode);
            },

            // Renderer lookup is now done via registry.async() in placeOrder()
            getPaymentRenderer: function (methodCode) {
                // This method is kept for compatibility but actual rendering now uses registry.async
                var registryKey = 'checkout.steps.billing-step.payment.payments-list.' + methodCode;
                var paymentRenderer = registry.get(registryKey);
                return (paymentRenderer && typeof paymentRenderer.placeOrder === 'function') ? paymentRenderer : null;
            },

            placeOrder: function () {
                var self = this;
                var desiredCustom = this.selectedMethod && this.selectedMethod();
                var desiredCode = this.resolveMagentoMethodCode(desiredCustom);
                var current = quote.paymentMethod() && quote.paymentMethod().method;
                var methodToPlace = desiredCode || current;

                if (!!window.tekntekPaymentDebug) {
                    console.log('[TeknTek][Payment] placeOrder', {
                        desiredCustom: desiredCustom,
                        desiredCode: desiredCode,
                        current: current,
                        available: this.getMethodList()
                    });
                    
                    // Debug: show all registry keys to understand structure
                    console.log('[TeknTek][Payment] DEBUG: Available registry keys:');
                    try {
                        var allRegistryData = registry.get();
                        if (typeof allRegistryData === 'object') {
                            var paymentRelatedKeys = Object.keys(allRegistryData).filter(function(key) {
                                return key.indexOf('payment') > -1 || key.indexOf('checkout.steps.billing') > -1;
                            });
                            console.log('[TeknTek][Payment] payment-related keys:', paymentRelatedKeys);
                        }
                    } catch (e) {
                        console.log('[TeknTek][Payment] Could not introspect registry:', e.message);
                    }
                }

                if (!this.isAvailableMethodCode(desiredCode)) {
                    if (!!window.tekntekPaymentDebug) {
                        console.log('[TeknTek][Payment] desiredCode not available, returning false');
                    }
                    return false;
                }

                if (desiredCode && current !== desiredCode) {
                    if (!!window.tekntekPaymentDebug) {
                        console.log('[TeknTek][Payment] changing payment method to:', desiredCode);
                    }
                    selectPaymentMethodAction({
                        method: desiredCode
                    });
                    checkoutData.setSelectedPaymentMethod(desiredCode);
                }

                if (!!window.tekntekPaymentDebug) {
                    console.log('[TeknTek][Payment] trying to get renderer for:', methodToPlace);
                }

                // If VNPay, attempt direct placeOrder flow (bypass registry if renderer can't be found)
                if (methodToPlace === 'vnpay') {
                    if (!!window.tekntekPaymentDebug) {
                        console.log('[TeknTek][Payment] using direct placeOrderAction for vnpay');
                    }

                    if (!additionalValidators.validate()) {
                        if (!!window.tekntekPaymentDebug) {
                            console.error('[TeknTek][Payment] additionalValidators failed');
                        }
                        return false;
                    }

                    // Ensure payment method selected in quote
                    if (current !== 'vnpay') {
                        selectPaymentMethodAction({ method: 'vnpay' });
                        checkoutData.setSelectedPaymentMethod('vnpay');
                    }

                    $.when(placeOrderAction({ method: 'vnpay', po_number: null, additional_data: null }))
                        .fail(function () {
                            if (!!window.tekntekPaymentDebug) {
                                console.error('[TeknTek][Payment] placeOrderAction failed');
                            }
                        })
                        .done(function (orderID) {
                            $.ajax({
                                url: window.location.pathname.slice(window.location.pathname.lastIndexOf, -9) + 'paymentvnpay/order/info?order_id=' + orderID
                            }).done(function (url) {
                                window.location.replace(url);
                            }).fail(function (err) {
                                if (!!window.tekntekPaymentDebug) {
                                    console.error('[TeknTek][Payment] vnpay redirect ajax failed', err);
                                }
                            });
                        });

                    return true;
                }

                if (methodToPlace === 'zalopay') {
                    if (!!window.tekntekPaymentDebug) {
                        console.log('[TeknTek][Payment] using direct placeOrderAction for zalopay');
                    }

                    if (!additionalValidators.validate()) {
                        if (!!window.tekntekPaymentDebug) {
                            console.error('[TeknTek][Payment] additionalValidators failed');
                        }
                        return false;
                    }

                    if (current !== 'zalopay') {
                        selectPaymentMethodAction({ method: 'zalopay' });
                        checkoutData.setSelectedPaymentMethod('zalopay');
                    }

                    $.when(placeOrderAction({ method: 'zalopay', po_number: null, additional_data: null }))
                        .fail(function () {
                            if (!!window.tekntekPaymentDebug) {
                                console.error('[TeknTek][Payment] ZaloPay placeOrderAction failed');
                            }
                        })
                        .done(function () {
                            window.location.replace(urlBuilder.build('zalopay/payment/redirect'));
                        });

                    return true;
                }

                // Use async registry to wait for renderer to be fully initialized
                var rendererKey = 'checkout.steps.billing-step.payment.payments-list.' + methodToPlace;
                var rendererFound = false;

                if (!!window.tekntekPaymentDebug) {
                    console.log('[TeknTek][Payment] waiting for renderer at key:', rendererKey);
                }

                registry.async(rendererKey)(function (renderer) {
                    rendererFound = true;
                    if (!!window.tekntekPaymentDebug) {
                        console.log('[TeknTek][Payment] ✓ renderer callback called for', methodToPlace);
                        console.log('[TeknTek][Payment] renderer object:', renderer);
                        console.log('[TeknTek][Payment] has placeOrder method:', renderer && typeof renderer.placeOrder === 'function');
                    }

                    if (renderer && typeof renderer.placeOrder === 'function') {
                        if (!!window.tekntekPaymentDebug) {
                            console.log('[TeknTek][Payment] ✓ calling renderer.placeOrder()...');
                        }
                        renderer.placeOrder();
                    } else {
                        if (!!window.tekntekPaymentDebug) {
                            console.error('[TeknTek][Payment] ✗ renderer.placeOrder is not a function!');
                        }
                    }
                });

                // Set timeout to check if renderer was found
                setTimeout(function () {
                    if (!rendererFound && !!window.tekntekPaymentDebug) {
                        console.error('[TeknTek][Payment] ✗ renderer NOT found after 2 seconds for key:', rendererKey);
                    }
                }, 2000);

                return true; // Return true immediately, actual placeOrder will happen async
            },

            isVnpay: function() {
                return this.selectedMethod() === 'vnpay';
            },

            isZaloPay: function() {
                return this.selectedMethod() === 'zalopay';
            },

            isCashOnDelivery: function() {
                return this.selectedMethod() === 'cashondelivery';
            }
        });
    }
);
