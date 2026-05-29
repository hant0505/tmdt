define([], function () {
    'use strict';

    return function (Target) {
        return Target.extend({
            /**
             * Force postcode to be optional for Vietnam.
             *
             * @param {String} value
             */
            update: function (value) {
                this._super(value);

                if (value === 'VN') {
                    this.error(false);
                    this.validation['required-entry'] = false;
                    this.required(false);
                }
            }
        });
    };
});
