define([
    'ko',
    'Magento_Customer/js/model/address-list'
], function (ko, addressList) {
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

                console.log('[LIST-VISIBILITY] Initialize, addressList length:', addressList().length);

                if (typeof this.visible !== 'function') {
                    this.visible = ko.observable(addressList().length > 0);
                }

                addressList.subscribe(function (items) {
                    console.log('[LIST-VISIBILITY] addressList subscriber fired, new length:', items.length);
                    console.log('[LIST-VISIBILITY] Updated visible to:', items.length > 0);
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
                    selected = null;

                console.log('[LIST-VISIBILITY] getSingleAddressRenderers called, total items:', items.length);

                items.some(function (item) {
                    if (item && typeof item.isSelected === 'function' && item.isSelected()) {
                        console.log('[LIST-VISIBILITY] Found selected item:', item);
                        selected = item;
                        return true;
                    }

                    return false;
                });

                if (selected) {
                    console.log('[LIST-VISIBILITY] Returning selected address');
                    return [selected];
                }

                console.log('[LIST-VISIBILITY] Returning first address (or empty)');
                return items.length ? [items[0]] : [];
            }
        });
    };
});