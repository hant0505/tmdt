define([], function () {
    'use strict';

    var uniqueCounter = 0;

    function toStringSafe(value) {
        return value === null || typeof value === 'undefined' ? '' : String(value);
    }

    function normalize(value) {
        return toStringSafe(value).trim().toLowerCase();
    }

    function joinStreet(street) {
        if (!Array.isArray(street)) {
            return normalize(street);
        }

        return street.map(normalize).join('|');
    }

    function buildStableAddressKey(addressData) {
        var data = addressData || {},
            parts = [
                normalize(data.firstname),
                normalize(data.lastname),
                normalize(data.company),
                joinStreet(data.street),
                normalize(data.city),
                normalize(data.postcode),
                normalize(data.country_id || data.countryId),
                normalize(data.region_id || data.regionId || data.region),
                normalize(data.telephone)
            ],
            hasRealData = parts.some(function (part) {
                return part.length > 0;
            });

        if (!hasRealData) {
            return 'new-customer-address-' + Date.now() + '-' + (++uniqueCounter);
        }

        return 'new-customer-address-' + parts.join('~');
    }

    return function (originalFactory) {
        return function (addressData) {
            var address = originalFactory(addressData),
                uniqueKey = buildStableAddressKey(addressData);

            // Keep each newly created address distinct AND stable for selection state checks.
            // Using getCacheKey() directly may change when address data mutates during checkout.
            address.getKey = function () {
                return uniqueKey;
            };

            return address;
        };
    };
});
