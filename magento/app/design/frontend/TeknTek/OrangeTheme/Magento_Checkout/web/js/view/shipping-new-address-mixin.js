define([
    'ko',
    'Magento_Customer/js/model/address-list'
], function (ko, addressList) {
    'use strict';

    return function (Target) {
        return Target.extend({
            /**
             * Keep checkout in single-address mode: allow creating new only when no address exists.
             *
             * @returns {Object}
             */
            initialize: function () {
                this._super();

                console.log('[SHIPPING-NEW-ADDRESS] Initialize, current addressList length:', addressList().length);

                this.canAddNewAddress = ko.observable(addressList().length === 0);

                addressList.subscribe(function (items) {
                    console.log('[SHIPPING-NEW-ADDRESS] addressList changed, new length:', items.length);
                    console.log('[SHIPPING-NEW-ADDRESS] Updated canAddNewAddress to:', items.length === 0);
                    this.canAddNewAddress(items.length === 0);
                }, this);

                return this;
            }
        });
    };
});