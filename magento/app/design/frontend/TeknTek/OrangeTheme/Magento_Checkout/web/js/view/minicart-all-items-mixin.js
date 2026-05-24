define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/action/get-totals'
], function ($, customerData, getTotalsAction) {
    'use strict';

    var controlsBound = false;

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
        getTotalsAction([], deferred);
        customerData.reload(['cart'], true);
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

                // Refresh minicart UI
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
                this._super();
                bindControls();

                return this;
            },

            /**
             * Return all cart items instead of slicing by maxItemsToDisplay.
             *
             * @returns {Array}
             */
            getCartItems: function () {
                return this.getCartParamUnsanitizedHtml('items') || [];
            }
        });
    };
});