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

                // Refresh cart data in-place without navigating away.
                refreshCartTotals();
            })
            .fail(function() {
                $input.val(currentQty);
                alert('Error updating cart. Please try again.');
            });
    }

    function commitQty($input) {
        var itemId = $input.data('cart-item');
        var currentQty = parseInt($input.val(), 10) || 1;
        var originalQty = parseInt($input.data('original-qty'), 10) || parseInt($input.data('item-qty'), 10) || 1;

        if (currentQty < 1) {
            $input.val(originalQty);
            return;
        }

        if (currentQty === originalQty) {
            return;
        }

        $input.data('original-qty', currentQty);
        $input.attr('data-item-qty', currentQty);

        updateCartItemQty(itemId, currentQty)
            .done(function(response) {
                if (response && response.error_message) {
                    $input.val(originalQty);
                    $input.data('original-qty', originalQty);
                    alert(response.error_message);
                    return;
                }

                // Refresh cart data in-place without navigating away.
                refreshCartTotals();
            })
            .fail(function() {
                $input.val(originalQty);
                $input.data('original-qty', originalQty);
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

        $(document).on('focus', '[data-block="minicart"] .cart-item-qty', function () {
            var $input = $(this);

            $input.data('original-qty', parseInt($input.val(), 10) || 1);
        });

        $(document).on('change blur', '[data-block="minicart"] .cart-item-qty', function () {
            commitQty($(this));
        });

        $(document).on('keydown', '[data-block="minicart"] .cart-item-qty', function (event) {
            if (event.key === 'Enter' || event.keyCode === 13) {
                event.preventDefault();
                commitQty($(event.currentTarget));
                event.currentTarget.blur();
            }
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