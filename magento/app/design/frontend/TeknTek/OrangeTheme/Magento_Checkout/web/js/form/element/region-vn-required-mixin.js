define([
    'uiRegistry'
], function (registry) {
    'use strict';

    return function (Target) {
        return Target.extend({
            /**
             * Force district/region to be required for Vietnam.
             *
             * @param {String} value
             */
            update: function (value) {
                this._super(value);

                if (value === 'VN') {
                    this.required(true);
                    this.validation['required-entry'] = true;

                    if (this.customName) {
                        registry.get(this.customName, function (input) {
                            if (!input) {
                                return;
                            }

                            input.visible(true);
                            input.required(true);
                            input.validation['required-entry'] = true;
                        });
                    }
                }
            }
        });
    };
});
