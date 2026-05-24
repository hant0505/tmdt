define(
    [
        'jquery',
        'ko',
        'uiComponent',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment-service',
        'Magento_Checkout/js/model/payment/method-list'
    ],
    function ($, ko, Component, quote, selectPaymentMethodAction, checkoutData, paymentService, methodList) {
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

                this.placeOrderVm = ko.observable(null);
                this.canPlaceOrder = ko.pureComputed(function () {
                    var vm = self.placeOrderVm();
                    var allowed;

                    if (!vm) {
                        return false;
                    }

                    allowed = vm.isPlaceOrderActionAllowed;

                    if (typeof allowed === 'function') {
                        return !!allowed();
                    }

                    return !!allowed;
                });

                this.isCashOnDeliveryAvailable = ko.pureComputed(function () {
                    var code = self.resolveMagentoMethodCode('cashondelivery');
                    return self.isAvailableMethodCode(code);
                });

                this.isVnpayAvailable = ko.pureComputed(function () {
                    var code = self.resolveMagentoMethodCode('vnpay');
                    return self.isAvailableMethodCode(code);
                });

                this.methodsLoaded = ko.pureComputed(function () {
                    return self.getMethodList().length > 0;
                });

                this.isCashOnDeliveryEnabled = ko.pureComputed(function () {
                    return !self.methodsLoaded() || self.isCashOnDeliveryAvailable();
                });

                this.isVnpayEnabled = ko.pureComputed(function () {
                    return !self.methodsLoaded() || self.isVnpayAvailable();
                });

                // Watch for changes in selected payment method
                this.selectedMethod.subscribe(function(newValue) {
                    self.showVnpayDetails(newValue === 'vnpay');
                    self.applySelectedMethod(newValue);
                    self.updatePlaceOrderViewModel();
                });

                methodList.subscribe(function () {
                    logDebug('Available methods updated', self.getMethodList());
                    self.ensureSelectedMethodAvailable();
                    self.updatePlaceOrderViewModel();
                });

                // Keep custom radio UI synced with the real Magento quote selection.
                quote.paymentMethod.subscribe(function (paymentMethod) {
                    var customCode = self.mapQuoteMethodToCustomOption(paymentMethod && paymentMethod.method);
                    if (customCode && self.selectedMethod() !== customCode) {
                        self.selectedMethod(customCode);
                    }

                    self.moveNativePlaceOrderToolbar();
                    self.updatePlaceOrderViewModel();
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
                this.updatePlaceOrderViewModel();
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
                    this.updatePlaceOrderViewModel();
                    return;
                }

                selectPaymentMethodAction({
                    method: methodCode
                });

                checkoutData.setSelectedPaymentMethod(methodCode);
                this.updatePlaceOrderViewModel();
            },

            updatePlaceOrderViewModel: function (attempt) {
                var desiredCustom = this.selectedMethod && this.selectedMethod();
                var desiredCode = this.resolveMagentoMethodCode(desiredCustom);
                var methodCode = (quote.paymentMethod() && quote.paymentMethod().method) || desiredCode;
                var $methodContainer;
                var vm;
                var self = this;

                attempt = attempt || 0;

                if (methodCode) {
                    $methodContainer = $('#payment .payment-method').has(
                        'input[type="radio"][name="payment[method]"][value="' + methodCode + '"]'
                    ).first();
                }

                if (!$methodContainer || !$methodContainer.length) {
                    $methodContainer = $('#payment .payment-method._active').first();
                }

                if ($methodContainer && $methodContainer.length) {
                    vm = ko.dataFor($methodContainer[0]);
                    if (vm && typeof vm.placeOrder === 'function') {
                        if (methodCode && typeof vm.getCode === 'function' && vm.getCode() !== methodCode) {
                            vm = null;
                        }
                    }

                    if (vm && typeof vm.placeOrder === 'function') {
                        this.placeOrderVm(vm);
                        return;
                    }
                }

                if (attempt < 20) {
                    window.setTimeout(function () {
                        self.updatePlaceOrderViewModel(attempt + 1);
                    }, 120);
                }
            },

            placeOrder: function () {
                var desiredCustom = this.selectedMethod && this.selectedMethod();
                var desiredCode = this.resolveMagentoMethodCode(desiredCustom);
                var current = quote.paymentMethod() && quote.paymentMethod().method;

                if (!!window.tekntekPaymentDebug) {
                    console.log('[TeknTek][Payment] placeOrder', {
                        desiredCustom: desiredCustom,
                        desiredCode: desiredCode,
                        current: current,
                        available: this.getMethodList()
                    });
                }

                if (!this.isAvailableMethodCode(desiredCode)) {
                    return false;
                }

                if (desiredCode && current !== desiredCode) {
                    selectPaymentMethodAction({
                        method: desiredCode
                    });
                    checkoutData.setSelectedPaymentMethod(desiredCode);
                }

                this.updatePlaceOrderViewModel();

                var vm = this.placeOrderVm();

                if (vm && typeof vm.placeOrder === 'function') {
                    return vm.placeOrder();
                }

                return false;
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
