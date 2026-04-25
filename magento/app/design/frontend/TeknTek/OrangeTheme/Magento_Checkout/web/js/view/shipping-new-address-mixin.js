define([
    'ko',
    'Magento_Customer/js/model/address-list',
    'Magento_Checkout/js/model/quote'
], function (ko, addressList, quote) {
    'use strict';

    var debugPrefix = '[TeknTek Checkout Debug]';

    function isDebugEnabled() {
        return typeof window !== 'undefined' && window.tekntekCheckoutDebug === true;
    }

    function debugLog(label, payload) {
        if (isDebugEnabled() && typeof window !== 'undefined' && window.console && window.console.log) {
            window.console.log(debugPrefix + ' ' + label, payload || {});
        }
    }

    function getAddressSummary(address) {
        if (!address) {
            return null;
        }

        return {
            key: typeof address.getKey === 'function' ? address.getKey() : null,
            firstname: address.firstname,
            lastname: address.lastname,
            street: address.street,
            city: address.city,
            postcode: address.postcode,
            countryId: address.countryId,
            region: address.region,
            regionId: address.regionId,
            telephone: address.telephone
        };
    }

    function getMethodSummary(method) {
        if (!method) {
            return null;
        }

        return {
            carrier_code: method.carrier_code,
            method_code: method.method_code,
            carrier_title: method.carrier_title,
            method_title: method.method_title
        };
    }

    function isNewCustomerAddress(address) {
        return address && typeof address.getType === 'function' && address.getType() === 'new-customer-address';
    }

    return function (Target) {
        return Target.extend({
            _isPruningNewCustomerAddresses: false,

            pruneDuplicateNewCustomerAddresses: function () {
                var selected = quote && typeof quote.shippingAddress === 'function' ? quote.shippingAddress() : null,
                    selectedKey = selected && typeof selected.getKey === 'function' ? selected.getKey() : null,
                    seenSelectedKey = false,
                    toRemove = [];

                if (this._isPruningNewCustomerAddresses) {
                    return;
                }

                addressList().forEach(function (address) {
                    var key;

                    if (!isNewCustomerAddress(address) || typeof address.getKey !== 'function') {
                        return;
                    }

                    key = address.getKey();

                    if (selectedKey && key === selectedKey) {
                        if (seenSelectedKey) {
                            toRemove.push(address);
                        } else {
                            seenSelectedKey = true;
                        }
                        return;
                    }

                    toRemove.push(address);
                });

                if (!toRemove.length) {
                    return;
                }

                this._isPruningNewCustomerAddresses = true;
                toRemove.forEach(function (address) {
                    addressList.remove(address);
                });
                this._isPruningNewCustomerAddresses = false;

                debugLog('pruneDuplicateNewCustomerAddresses', {
                    removedCount: toRemove.length,
                    addressListLength: addressList().length
                });
            },

            /**
             * Keep checkout in single-address mode: allow creating new only when no address exists.
             *
             * @returns {Object}
             */
            initialize: function () {
                this._super();

                this.canAddNewAddress = ko.observable(addressList().length === 0);

                debugLog('initialize', {
                    addressListLength: addressList().length,
                    selectedShippingAddress: quote && typeof quote.shippingAddress === 'function' ?
                        getAddressSummary(quote.shippingAddress()) : null,
                    selectedShippingMethod: quote && typeof quote.shippingMethod === 'function' ?
                        getMethodSummary(quote.shippingMethod()) : null
                });

                addressList.subscribe(function (items) {
                    debugLog('addressList.changed', {
                        length: items.length,
                        canAddNewAddress: items.length === 0
                    });

                    this.pruneDuplicateNewCustomerAddresses();
                    this.canAddNewAddress(items.length === 0);
                }, this);

                if (quote && typeof quote.shippingAddress === 'function' && quote.shippingAddress.subscribe) {
                    quote.shippingAddress.subscribe(function (address) {
                        debugLog('quote.shippingAddress.changed', getAddressSummary(address));
                    });
                }

                if (quote && typeof quote.shippingMethod === 'function' && quote.shippingMethod.subscribe) {
                    quote.shippingMethod.subscribe(function (method) {
                        debugLog('quote.shippingMethod.changed', getMethodSummary(method));
                    });
                }

                if (typeof this.isFormPopUpVisible === 'function' && this.isFormPopUpVisible.subscribe) {
                    this.isFormPopUpVisible.subscribe(function (visible) {
                        debugLog('popup.visibility.changed', { visible: visible });
                    });
                }

                if (this.rates && this.rates.subscribe) {
                    this.rates.subscribe(function (rates) {
                        debugLog('shippingRates.changed', {
                            count: rates ? rates.length : 0,
                            methods: (rates || []).map(function (rate) {
                                return getMethodSummary(rate);
                            })
                        });
                    });
                }

                this.pruneDuplicateNewCustomerAddresses();

                return this;
            },

            /**
             * Add debug visibility for popup save action.
             *
             * @return {Boolean|undefined}
             */
            saveNewAddress: function () {
                debugLog('saveNewAddress.start', {
                    sourceShippingAddress: this.source && this.source.get ? this.source.get('shippingAddress') : null
                });

                var result = this._super();

                debugLog('saveNewAddress.end', {
                    result: result,
                    selectedShippingAddress: quote && typeof quote.shippingAddress === 'function' ?
                        getAddressSummary(quote.shippingAddress()) : null,
                    popupVisible: typeof this.isFormPopUpVisible === 'function' ? this.isFormPopUpVisible() : null
                });

                this.pruneDuplicateNewCustomerAddresses();

                return result;
            },

            /**
             * Log validation state and prevent stuck Next when exactly one rate is available but not selected.
             *
             * @return {Boolean}
             */
            setShippingInformation: function () {
                var rates = this.rates && typeof this.rates === 'function' ? this.rates() : [];

                if (!quote.shippingMethod() && rates && rates.length === 1 && typeof this.selectShippingMethod === 'function') {
                    this.selectShippingMethod(rates[0]);
                    debugLog('setShippingInformation.autoSelectedSingleRate', getMethodSummary(rates[0]));
                }

                debugLog('setShippingInformation.start', {
                    selectedShippingAddress: quote && typeof quote.shippingAddress === 'function' ?
                        getAddressSummary(quote.shippingAddress()) : null,
                    selectedShippingMethod: quote && typeof quote.shippingMethod === 'function' ?
                        getMethodSummary(quote.shippingMethod()) : null,
                    ratesCount: rates ? rates.length : 0
                });

                return this._super();
            },

            /**
             * Log validate result for easier troubleshooting.
             *
             * @return {Boolean}
             */
            validateShippingInformation: function () {
                var result = this._super();

                debugLog('validateShippingInformation.result', {
                    result: result,
                    errorValidationMessage: typeof this.errorValidationMessage === 'function' ?
                        this.errorValidationMessage() : null,
                    selectedShippingAddress: quote && typeof quote.shippingAddress === 'function' ?
                        getAddressSummary(quote.shippingAddress()) : null,
                    selectedShippingMethod: quote && typeof quote.shippingMethod === 'function' ?
                        getMethodSummary(quote.shippingMethod()) : null
                });

                return result;
            }
        });
    };
});