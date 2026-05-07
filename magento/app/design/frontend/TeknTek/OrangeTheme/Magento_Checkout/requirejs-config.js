var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/minicart': {
                'Magento_Checkout/js/view/minicart-all-items-mixin': true
            },
            'Magento_Checkout/js/view/shipping': {
                'Magento_Checkout/js/view/shipping-new-address-mixin': true
            },
            'Magento_Checkout/js/model/new-customer-address': {
                'Magento_Checkout/js/model/new-customer-address-mixin': true
            },
            'Magento_Checkout/js/view/shipping-address/list': {
                'Magento_Checkout/js/view/shipping-address/list-visibility-mixin': true
            },
            'Magento_Checkout/js/view/shipping-methods/default': {
                'Magento_Checkout/js/view/shipping-methods-mixin': true
            }
        }
    }
};