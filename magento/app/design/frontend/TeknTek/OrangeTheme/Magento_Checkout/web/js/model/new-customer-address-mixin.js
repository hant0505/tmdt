define([], function () {
    'use strict';

    var SINGLE_CHECKOUT_ADDRESS_KEY = 'new-customer-address-single';

    return function (originalFactory) {
        return function (addressData) {
            var address = originalFactory(addressData);

            // Single-address checkout mode: edit should update the same address entry.
            address.getKey = function () {
                return SINGLE_CHECKOUT_ADDRESS_KEY;
            };

            return address;
        };
    };
});
