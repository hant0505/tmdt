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
            'Magento_Ui/js/form/element/post-code': {
                'Magento_Checkout/js/form/element/post-code-vn-optional-mixin': true
            },
            'Magento_Ui/js/form/element/region': {
                'Magento_Checkout/js/form/element/region-vn-required-mixin': true
            },
            'Magento_Checkout/js/view/shipping-address/list': {
                'Magento_Checkout/js/view/shipping-address/list-visibility-mixin': true
            },
            'Magento_Checkout/js/view/shipping-methods/default': {
                'Magento_Checkout/js/view/shipping-methods-mixin': true
            },
            'Magento_OfflinePayments/js/view/payment/method-renderer/cashondelivery-method': {
                'Magento_Checkout/js/view/payment/cashondelivery-mixin': true
            },
            'Magento_SalesRule/js/view/payment/discount': {
                'Magento_Checkout/js/view/payment/discount-modal-mixin': true
            }
        }
    }
};
