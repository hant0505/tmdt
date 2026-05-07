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

                if (typeof this.visible !== 'function') {
                    this.visible = ko.observable(addressList().length > 0);
                }

                addressList.subscribe(function (items) {
                    this.visible(items.length > 0);
                }, this);

                return this;
            }
        });
    };
});