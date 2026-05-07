define([], function () {
    'use strict';

    var uniqueCounter = 0;

    return function (originalFactory) {
        return function (addressData) {
            var address = originalFactory(addressData),
                uniqueKey = 'new-customer-address-' + Date.now() + '-' + (++uniqueCounter);

            // Keep each newly created address distinct AND stable for selection state checks.
            // Using getCacheKey() directly may change when address data mutates during checkout.
            address.getKey = function () {
                return uniqueKey;
            };

            return address;
        };
    };
});
