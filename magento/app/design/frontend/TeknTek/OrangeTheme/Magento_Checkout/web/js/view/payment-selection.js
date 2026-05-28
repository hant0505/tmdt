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
        'uiRegistry'
    ],
    function ($, ko, Component, quote, selectPaymentMethodAction, checkoutData, paymentService, methodList, registry) {
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
                    logDebug('selectedMethod changed', newValue);
                    self.showVnpayDetails(newValue === 'vnpay');
                    self.applySelectedMethod(newValue);
                    self.updatePlaceOrderViewModel();
                });

                methodList.subscribe(function () {
                    logDebug('Available methods updated', self.getMethodList());
                    
                    // Auto-select preferred payment method when methods load
                    self.selectInitialPaymentMethod();
                    
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
                this.selectInitialPaymentMethod();
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

                fallback = this.getPreferredInitialMethodCode() || methods[0].method;
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

            getPreferredInitialMethodCode: function () {
                if (this.isAvailableMethodCode('vnpay')) {
                    return this.resolveMagentoMethodCode('vnpay');
                }

                if (this.isAvailableMethodCode('cashondelivery')) {
                    return this.resolveMagentoMethodCode('cashondelivery');
                }

                return (this.getMethodList()[0] && this.getMethodList()[0].method) || null;
            },

            selectInitialPaymentMethod: function () {
                var methods = this.getMethodList();
                var current = quote.paymentMethod() && quote.paymentMethod().method;
                var preferred = this.getPreferredInitialMethodCode();
                var customCode;

                if (!methods.length || !preferred || current === preferred) {
                    return;
                }

                console.log('[AUTO-SELECT] Selecting preferred method:', preferred, 'Available:', methods.length);

                selectPaymentMethodAction({
                    method: preferred
                });
                checkoutData.setSelectedPaymentMethod(preferred);

                customCode = this.mapQuoteMethodToCustomOption(preferred);
                if (customCode) {
                    this.selectedMethod(customCode);
                    this.showVnpayDetails(customCode === 'vnpay');
                }
            },

            applySelectedMethod: function (desiredCode) {
                var methodCode = this.resolveMagentoMethodCode(desiredCode);
                var current = quote.paymentMethod();

                if (!!window.tekntekPaymentDebug) {
                    console.log('[TeknTek][Payment] applySelectedMethod', { desiredCode: desiredCode, resolved: methodCode, current: current });
                }

                if (!methodCode) {
                    return;
                }

                if (!this.isAvailableMethodCode(methodCode)) {
                    this.ensureSelectedMethodAvailable();
                    return;
                }

                if (current && current.method === methodCode) {
                    if (!!window.tekntekPaymentDebug) {
                        console.log('[TeknTek][Payment] applySelectedMethod - already selected:', methodCode);
                    }
                    checkoutData.setSelectedPaymentMethod(methodCode);
                    this.updatePlaceOrderViewModel();
                    return;
                }

                if (!!window.tekntekPaymentDebug) {
                    console.log('[TeknTek][Payment] applySelectedMethod - selecting method via action', methodCode);
                }

                selectPaymentMethodAction({
                    method: methodCode
                });

                checkoutData.setSelectedPaymentMethod(methodCode);
                // Wait for Magento to update the authoritative quote.paymentMethod, then sync the native radio and VM.
                var synced = false;
                var maxWait = 1800; // ms
                var start = Date.now();

                function syncRadioAndVm() {
                    try {
                        var $radio = $('input[type="radio"][name="payment[method]"][value="' + methodCode + '"]');
                        if ($radio.length) {
                            $radio.prop('checked', true);
                            $radio.trigger('click');
                            $radio.trigger('change');
                            if (!!window.tekntekPaymentDebug) {
                                console.log('[TeknTek][Payment] applySelectedMethod - synced radio and triggered events for', methodCode, $radio[0]);
                            }
                        } else if (!!window.tekntekPaymentDebug) {
                            console.log('[TeknTek][Payment] applySelectedMethod - radio not in DOM for', methodCode);
                        }
                    } catch (e) {
                        console.log('[TeknTek][Payment] applySelectedMethod - error syncing radio', e);
                    }

                    // Ensure VM updated after radio sync
                    try {
                        this.updatePlaceOrderViewModel();
                    } catch (e) {
                        // ignore
                    }
                }

                var self = this;
                var subscription = quote.paymentMethod.subscribe(function (pm) {
                    if (pm && pm.method === methodCode) {
                        if (!synced) {
                            synced = true;
                            syncRadioAndVm.call(self);
                        }
                        try { subscription.dispose(); } catch (e) {}
                    } else {
                        // if timeout exceeded, try to sync anyway once
                        if (!synced && Date.now() - start > maxWait) {
                            synced = true;
                            syncRadioAndVm.call(self);
                            try { subscription.dispose(); } catch (e) {}
                        }
                    }
                });

                // If payment method was already set in quote, sync immediately
                var currentPm = quote.paymentMethod();
                if (currentPm && currentPm.method === methodCode) {
                    try { subscription.dispose(); } catch (e) {}
                    syncRadioAndVm.call(self);
                }
            },

            updatePlaceOrderViewModel: function (attempt) {
                var desiredCustom = this.selectedMethod && this.selectedMethod();
                var desiredCode = this.resolveMagentoMethodCode(desiredCustom);
                var methodCode = desiredCode || (quote.paymentMethod() && quote.paymentMethod().method);
                var registryName = methodCode ? ('checkout.steps.billing-step.payment.payments-list.' + methodCode) : null;
                var $methodContainer;
                var vm;
                var self = this;

                attempt = attempt || 0;
                
                var debugEnabled = !!window.tekntekPaymentDebug;

                if (methodCode) {
                    $methodContainer = $('#payment .payment-method').has(
                        'input[type="radio"][name="payment[method]"][value="' + methodCode + '"]'
                    ).first();
                    
                    if (debugEnabled && attempt === 0) {
                        console.log('[updatePlaceOrderViewModel] Looking for methodCode:', methodCode);
                        console.log('[updatePlaceOrderViewModel] Found $methodContainer:', $methodContainer.length, 'components');
                    }
                }

                if (!$methodContainer || !$methodContainer.length) {
                    $methodContainer = $('#payment .payment-method._active').first();
                    
                    if (debugEnabled && attempt === 0) {
                        console.log('[updatePlaceOrderViewModel] Fallback to _active, found:', $methodContainer.length);
                    }
                }

                if (registryName) {
                    vm = registry.get(registryName);

                    if (debugEnabled && attempt === 0) {
                        console.log('[updatePlaceOrderViewModel] Registry lookup:', registryName, vm ? vm : 'not found');
                    }

                    if (vm && typeof vm.placeOrder === 'function') {
                        if (debugEnabled) {
                            console.log('[updatePlaceOrderViewModel] Using payment VM from registry:', typeof vm.getCode === 'function' ? vm.getCode() : 'no getCode', vm);
                        }
                    } else {
                        vm = null;
                    }
                }

                if (!vm && $methodContainer && $methodContainer.length) {
                    vm = ko.dataFor($methodContainer[0]);
                    if (debugEnabled) {
                        console.log('[updatePlaceOrderViewModel] Got vm from container (initial):', vm ? (typeof vm.getCode === 'function' ? vm.getCode() : 'no getCode') : 'null', vm);
                    }

                    // If the vm we found is not the payment-method vm (some themes wrap elements),
                    // search descendants for a vm that implements placeOrder/getCode.
                    if (!(vm && typeof vm.placeOrder === 'function') || (methodCode && typeof vm.getCode === 'function' && vm.getCode() !== methodCode)) {
                        var found = null;
                        $methodContainer.find('*').each(function () {
                            try {
                                var deepVm = ko.dataFor(this);
                                if (deepVm && typeof deepVm.placeOrder === 'function') {
                                    if (!methodCode || (typeof deepVm.getCode === 'function' && deepVm.getCode() === methodCode)) {
                                        found = deepVm;
                                        return false; // break
                                    }
                                }
                            } catch (e) {
                                // ignore
                            }
                        });

                        if (found) {
                            vm = found;
                            if (debugEnabled) {
                                console.log('[updatePlaceOrderViewModel] Found payment VM in descendants:', typeof vm.getCode === 'function' ? vm.getCode() : 'no getCode', vm);
                            }
                        } else {
                            if (debugEnabled) {
                                console.log('[updatePlaceOrderViewModel] No payment VM found in descendants for methodCode:', methodCode);
                            }
                            vm = null;
                        }
                    }

                    if (vm && typeof vm.placeOrder === 'function') {
                        this.placeOrderVm(vm);
                        if (debugEnabled) {
                            try {
                                var vmCode = typeof vm.getCode === 'function' ? vm.getCode() : '(no getCode)';
                                var canPlace = typeof vm.isPlaceOrderActionAllowed === 'function' ? !!vm.isPlaceOrderActionAllowed() : (vm.isPlaceOrderActionAllowed !== undefined ? !!vm.isPlaceOrderActionAllowed : '(no flag)');
                                console.log('[updatePlaceOrderViewModel] ✓ Set placeOrderVm successfully', { vmCode: vmCode, canPlace: canPlace, vm: vm });
                            } catch (e) {
                                console.log('[updatePlaceOrderViewModel] ✓ Set placeOrderVm successfully (could not inspect vm)', vm);
                            }
                        }
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
                    console.log('[TeknTek][Payment] placeOrder - code not available:', desiredCode);
                    return false;
                }

                if (desiredCode && current !== desiredCode) {
                    selectPaymentMethodAction({
                        method: desiredCode
                    });
                    checkoutData.setSelectedPaymentMethod(desiredCode);
                }

                // Ensure we have the correct placeOrder view model before invoking.
                var self = this;
                var attempts = 0;
                var maxAttempts = 15; // ~1.8s max wait

                this.updatePlaceOrderViewModel();

                return (function waitForVm() {
                    var vm = self.placeOrderVm();
                    if (vm && typeof vm.placeOrder === 'function') {
                        // If vm exposes getCode, ensure it matches desiredCode when available
                        if (typeof vm.getCode === 'function') {
                            try {
                                var vmCode = vm.getCode();
                                if (vmCode && desiredCode && vmCode !== desiredCode) {
                                    // mismatch, keep waiting for correct vm
                                    if (attempts++ < maxAttempts) {
                                        return new Promise(function (resolve) {
                                            setTimeout(function () { resolve(waitForVm()); }, 120);
                                        });
                                    }
                                    console.log('[TeknTek][Payment] placeOrder - VM code mismatch after wait:', vmCode, 'expected:', desiredCode);
                                    return false;
                                }
                            } catch (e) {
                                // ignore getCode errors
                            }
                        }

                        console.log('[TeknTek][Payment] placeOrder - calling vm.placeOrder()');
                        try {
                            return vm.placeOrder();
                        } catch (e) {
                            console.log('[TeknTek][Payment] placeOrder - vm.placeOrder threw error', e);
                            return false;
                        }
                    }

                    if (attempts++ < maxAttempts) {
                        return new Promise(function (resolve) {
                            setTimeout(function () { resolve(waitForVm()); }, 120);
                        });
                    }

                    console.log('[TeknTek][Payment] placeOrder - NO vm or no placeOrder function after waiting');
                    return false;
                }());
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
