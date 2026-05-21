define([
    'jquery',
    'ko',
    'Magento_Customer/js/model/address-list',
    'Magento_Checkout/js/model/quote'
], function ($, ko, addressList, quote) {
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

    function extractAddressValue(value) {
        var candidateKeys = ['value', 'label', 'text', 'street', 'name'],
            index,
            extracted;

        if (value === null || value === undefined) {
            return '';
        }

        if (typeof value === 'function') {
            try {
                return extractAddressValue(value());
            } catch (e) {
                return '';
            }
        }

        if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
            return value.toString().trim();
        }

        if (Array.isArray(value)) {
            return value.map(function (entry) {
                return extractAddressValue(entry);
            }).join(' ').trim();
        }

        if (typeof value === 'object') {
            for (index = 0; index < candidateKeys.length; index++) {
                if (Object.prototype.hasOwnProperty.call(value, candidateKeys[index])) {
                    extracted = extractAddressValue(value[candidateKeys[index]]);
                    if (extracted) {
                        return extracted;
                    }
                }
            }
        }

        return '';
    }

    function normalizeStreetLines(street) {
        var lines;

        function normalizeAndLimit(inputLines) {
            var normalized = (inputLines || []).map(function (line) {
                return extractAddressValue(line);
            });

            return normalized.slice(0, 2);
        }

        if (!street) {
            return ['', ''];
        }

        if (!Array.isArray(street) && typeof street === 'object') {
            lines = normalizeAndLimit(Object.keys(street)
                .sort(function (a, b) {
                    var aNum = parseInt(a, 10),
                        bNum = parseInt(b, 10),
                        aIsNum = !isNaN(aNum),
                        bIsNum = !isNaN(bNum);

                    if (aIsNum && bIsNum) {
                        return aNum - bNum;
                    }

                    if (aIsNum) {
                        return -1;
                    }

                    if (bIsNum) {
                        return 1;
                    }

                    return a.localeCompare(b);
                })
                .map(function (key) {
                    return extractAddressValue(street[key]);
                }));

            while (lines.length < 2) {
                lines.push('');
            }

            return lines;
        }

        if (!Array.isArray(street)) {
            return [extractAddressValue(street), ''];
        }

        lines = normalizeAndLimit(street);

        while (lines.length < 2) {
            lines.push('');
        }

        return lines;
    }

    function resolveRegionAsCity(addressData, source) {
        var regionValue = addressData && addressData.region,
            sourceRegion = source && source.get ? source.get('shippingAddress.region') : null;

        if (regionValue && typeof regionValue === 'object') {
            regionValue = regionValue.region || regionValue.label || regionValue.regionCode || '';
        }

        if ((!regionValue || !regionValue.toString().trim().length) && sourceRegion) {
            regionValue = sourceRegion;
        }

        if (regionValue && typeof regionValue === 'object') {
            regionValue = regionValue.region || regionValue.label || regionValue.regionCode || '';
        }

        return (regionValue || '').toString().trim();
    }

    function getSourceValue(source, path) {
        var value;

        if (!source || typeof source.get !== 'function') {
            return '';
        }

        value = source.get(path);

        if (typeof value === 'function') {
            try {
                value = value();
            } catch (e) {
                value = '';
            }
        }

        return extractAddressValue(value);
    }

    function resolveStreetFromSource(addressData, source) {
        var streetData = addressData && addressData.street,
            line0,
            line1,
            lines;

        line0 = getSourceValue(source, 'shippingAddress.street.0');
        line1 = getSourceValue(source, 'shippingAddress.street.1');

        if ((line0 || line1)) {
            return [line0, line1];
        }

        if (streetData && typeof streetData === 'object' && !Array.isArray(streetData)) {
            line0 = extractAddressValue(streetData[0] || streetData['0']);
            line1 = extractAddressValue(streetData[1] || streetData['1']);

            if (line0 || line1) {
                return [line0, line1];
            }
        }

        lines = normalizeStreetLines(streetData);

        return [lines[0] || '', lines[1] || ''];
    }

    function getStreetLineFromDom(formElement, index) {
        var selectors = [
                "input[name='street[" + index + "]']",
                "input[name='street." + index + "']",
                "input[name='shippingAddress.street." + index + "']",
                "input[name$='street[" + index + "]']",
                "input[name$='street." + index + "']"
            ],
            input,
            selector = selectors.join(',');

        if (formElement) {
            input = $(formElement).find(selector);
        }

        if (!input || !input.length) {
            input = $(selector);
        }

        if (!input.length) {
            return '';
        }

        return extractAddressValue(input.first().val());
    }

    function resolveStreetFromDom(addressData, source, context) {
        var formElement = context && context.popUpForm ? context.popUpForm.element : null,
            line0 = getStreetLineFromDom(formElement, 0),
            line1 = getStreetLineFromDom(formElement, 1),
            sourceStreet = addressData && addressData.street,
            sourceLines;

        if (line0 || line1) {
            return [line0, line1];
        }

        if (sourceStreet && Array.isArray(sourceStreet)) {
            sourceLines = normalizeStreetLines(sourceStreet);
            return [sourceLines[0] || '', sourceLines[1] || ''];
        }

        return resolveStreetFromSource(addressData, source);
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