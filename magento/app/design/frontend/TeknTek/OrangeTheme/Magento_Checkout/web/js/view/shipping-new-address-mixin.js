define([
    'jquery',
    'uiRegistry',
    'Magento_Checkout/js/checkout-data',
    'Magento_Customer/js/model/address-list',
    'Magento_Checkout/js/action/create-shipping-address',
    'Magento_Checkout/js/action/select-shipping-address'
], function ($, registry, checkoutData, addressList, createShippingAddress, selectShippingAddress) {
    'use strict';
//DEBUG nhe
    var debugPrefix = '[TeknTek Checkout Debug]';

    function debugLog(label, payload) {
        if (typeof window !== 'undefined' && window.console && window.console.log) {
            window.console.log(debugPrefix + ' ' + label, payload || {});
        }
    }

    return function (Target) {
        return Target.extend({
            isCreatingNewAddressFromRegister: false,

            /**
             * Ensure popup action label matches requested UX.
             *
             * @returns {Object}
             */
            initialize: function () {
                this._super();

                debugLog('shipping-mixin.initialize', {
                    addressListLength: addressList().length,
                    isFormInline: this.isFormInline,
                    isNewAddressAdded: this.isNewAddressAdded && this.isNewAddressAdded()
                });

                if (
                    this.popUpForm &&
                    this.popUpForm.options &&
                    this.popUpForm.options.buttons &&
                    this.popUpForm.options.buttons.save
                ) {
                    this.popUpForm.options.buttons.save.text = 'Save';
                }

                return this;
            },

            /**
             * Open popup in create-new mode to avoid editing existing address data.
             */
            openNewAddressForm: function () {
                var provider = registry.get('checkoutProvider'),
                    emptyShippingAddress;

                debugLog('openNewAddressForm.start', {
                    addressListLength: addressList().length,
                    selectedShippingAddress: checkoutData.getSelectedShippingAddress()
                });

                emptyShippingAddress = {
                    firstname: '',
                    lastname: '',
                    company: '',
                    street: ['', ''],
                    city: '',
                    postcode: '',
                    country_id: (window.checkoutConfig && window.checkoutConfig.defaultCountryId) || '',
                    region: '',
                    region_id: '',
                    telephone: '',
                    customerAddressId: null,
                    customer_id: null,
                    save_in_address_book: 1,
                    custom_attributes: []
                };

                if (provider) {
                    provider.set('shippingAddress', {});
                    provider.set('shippingAddress', emptyShippingAddress);
                }

                checkoutData.setShippingAddressFromData(emptyShippingAddress);
                checkoutData.setNewCustomerShippingAddress(emptyShippingAddress);
                this.isCreatingNewAddressFromRegister = true;
                this.isFormPopUpVisible(true);

                debugLog('openNewAddressForm.end', {
                    isCreatingNewAddressFromRegister: this.isCreatingNewAddressFromRegister,
                    formVisible: this.isFormPopUpVisible && this.isFormPopUpVisible()
                });
            },

            /**
             * Reset register flag when popup closes/cancels.
             */
            onClosePopUp: function () {
                debugLog('onClosePopUp', {
                    wasCreatingFromRegister: this.isCreatingNewAddressFromRegister,
                    addressListLength: addressList().length
                });
                this.isCreatingNewAddressFromRegister = false;
                return this._super();
            },

            /**
             * @return {Boolean}
             */
            saveNewAddress: function () {
                var addressData,
                    newAddressData,
                    newAddress;

                console.log('[TeknTek Checkout Debug] saveNewAddress.start');
                this.source.set('params.invalid', false);
                this.triggerShippingDataValidateEvent();

                if (this.source.get('params.invalid')) {
                    console.log('[TeknTek Checkout Debug] saveNewAddress.validation_failed');
                    return;
                }

                addressData = this.source.get('shippingAddress') || {};

                // Build a fresh payload in Magento schema and remove all existing address identifiers.
                newAddressData = $.extend(true, {}, addressData);
                newAddressData.country_id = newAddressData.country_id || newAddressData.countryId || '';
                newAddressData.region_id = newAddressData.region_id || newAddressData.regionId || '';
                newAddressData.save_in_address_book = this.saveInAddressBook ? 1 : 0;

                delete newAddressData.countryId;
                delete newAddressData.regionId;
                delete newAddressData.saveInAddressBook;
                delete newAddressData.customerAddressId;
                delete newAddressData.customer_address_id;
                delete newAddressData.address_id;
                delete newAddressData.id;

                console.log('[TeknTek Checkout Debug] saveNewAddress.payload', newAddressData);

                newAddress = createShippingAddress(newAddressData);

                // select shipping address
                selectShippingAddress(newAddress);
                checkoutData.setSelectedShippingAddress(newAddress.getKey());
                checkoutData.setNewCustomerShippingAddress($.extend(true, {}, newAddressData));
                this.getPopUp().closeModal();
                this.isNewAddressAdded(true);
                console.log('[TeknTek Checkout Debug] saveNewAddress.end. Address list length:', addressList().length);
            }
        });
    };
});