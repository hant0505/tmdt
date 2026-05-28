define([
    'jquery',
    'Magento_Customer/js/customer-data'
], function ($, customerData) {
    'use strict';

    var controlsBound = false;
    var missingItemsRefresh = false;

    function getFormKey() {
        var cookieMatch;

        if (window.FORM_KEY) {
            return window.FORM_KEY;
        }

        cookieMatch = document.cookie.match(/(?:^|; )form_key=([^;]+)/);
        return cookieMatch ? decodeURIComponent(cookieMatch[1]) : '';
    }

    function refreshCartTotals() {
        var deferred = $.Deferred();
        if (window.checkoutConfig && window.checkoutConfig.totalsData) {
            require([
                'Magento_Checkout/js/action/get-totals'
            ], function (getTotalsAction) {
                getTotalsAction([], deferred);
                customerData.reload(['cart'], true);
            }, function () {
                customerData.reload(['cart'], true);
                deferred.resolve();
            });
        } else {
            customerData.reload(['cart'], true);
            deferred.resolve();
        }
        return deferred.promise();
    }

    function updateCartItemQty(itemId, qty) {
        var payload = {
            item_id: itemId,
            item_qty: qty,
            form_key: getFormKey()
        };

        return $.ajax({
            url: '/checkout/sidebar/updateItemQty',
            type: 'POST',
            data: payload,
            showLoader: true
        });
    }

    function updateQty($input, delta) {
        var currentQty = parseInt($input.val(), 10) || 1;
        var nextQty = Math.max(1, currentQty + delta);
        var itemId = $input.data('cart-item');

        if (nextQty === currentQty) {
            return;
        }

        $input.val(nextQty);

        updateCartItemQty(itemId, nextQty)
            .done(function(response) {
                if (response && response.error_message) {
                    $input.val(currentQty);
                    alert(response.error_message);
                    return;
                }

                if (window.location && window.location.pathname.indexOf('/checkout/cart') !== -1) {
                    window.location.reload();
                    return;
                }

                // Refresh minicart UI on non-cart pages.
                refreshCartTotals();
            })
            .fail(function() {
                $input.val(currentQty);
                alert('Error updating cart. Please try again.');
            });
    }

    function bindControls() {
        if (controlsBound) {
            return;
        }

        controlsBound = true;

        $(document).on('click', '[data-block="minicart"] [data-action="qty-minus"]', function (event) {
            event.preventDefault();
            updateQty($(event.currentTarget).siblings('.cart-item-qty'), -1);
        });

        $(document).on('click', '[data-block="minicart"] [data-action="qty-plus"]', function (event) {
            event.preventDefault();
            updateQty($(event.currentTarget).siblings('.cart-item-qty'), 1);
        });
    }

    return function (Target) {
        return Target.extend({
            checkoutUrl: window.checkout && window.checkout.checkoutUrl ? window.checkout.checkoutUrl : '/checkout',

            initialize: function () {
                var cartSection;

                this._super();
                bindControls();

                cartSection = customerData.get('cart');
                cartSection.subscribe(function (data) {
                    if (!data || missingItemsRefresh) {
                        return;
                    }

                    if (data.summary_count && (!data.items || !data.items.length)) {
                        missingItemsRefresh = true;
                        customerData.reload(['cart'], true);
                    }
                });

                return this;
            },

            /**
             * Return all cart items instead of slicing by maxItemsToDisplay.
             *
             * @returns {Array}
             */
            getCartItems: function () {
                var items = this.getCartParamUnsanitizedHtml('items') || [];

                if (!Array.isArray(items)) {
                    items = Object.keys(items || {}).map(function (key) {
                        return items[key];
                    });
                }

                return items;
            }
        });
    };
});