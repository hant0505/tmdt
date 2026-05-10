define([
    'ko',
    'Magento_Customer/js/model/address-list',
    'Magento_Checkout/js/model/quote'
], function (ko, addressList, quote) {
    'use strict';

    return function (Target) {
        return Target.extend({
            /**
             * Keep list visibility reactive when a new shipping address is added.
             *
             * @returns {Object}
             */
            initialize: function () {
                this._super();

                if (typeof this.visible !== 'function') {
                    this.visible = ko.observable(addressList().length > 0);
                }

                addressList.subscribe(function (items) {
                    this.visible(items.length > 0);
                }, this);

                return this;
            },

            /**
             * Render only one address card: selected address first, otherwise first available.
             *
             * @returns {Array}
             */
            getSingleAddressRenderers: function () {
                var items = typeof this.elems === 'function' ? this.elems() : this.elems || [],
                    selected = null,
                    selectedQuoteAddress = typeof quote.shippingAddress === 'function' ? quote.shippingAddress() : null;

                items.some(function (item) {
                    if (item && typeof item.isSelected === 'function' && item.isSelected()) {
                        selected = item;
                        return true;
                    }

                    return false;
                });

                if (selected) {
                    if (
                        selectedQuoteAddress &&
                        typeof selected.address === 'function' &&
                        selected.address() &&
                        typeof selected.address().getKey === 'function' &&
                        typeof selectedQuoteAddress.getKey === 'function' &&
                        selected.address().getKey() === selectedQuoteAddress.getKey()
                    ) {
                        selected.address(selectedQuoteAddress);
                    }

                    return [selected];
                }
                return items.length ? [items[0]] : [];
            }
        });
    };
});