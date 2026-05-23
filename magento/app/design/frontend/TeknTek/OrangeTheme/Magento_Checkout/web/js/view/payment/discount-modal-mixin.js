define([
    'jquery'
], function ($) {
    'use strict';

    return function (Target) {
        return Target.extend({
            initialize: function () {
                this._super();

                var overlayClass = 'tekntek-coupon-modal-overlay';
                var openClass = 'tekntek-coupon-modal-open';
                var maxAttempts = 30;

                function ensureOverlay() {
                    if (!$('.' + overlayClass).length) {
                        $('body').append('<div class="' + overlayClass + '" aria-hidden="true"></div>');
                    }
                }

                function syncModalState() {
                    var isOpen = $('.checkout-index-index #payment .payment-option.discount-code').hasClass('_active');
                    $('body').toggleClass(openClass, isOpen);
                }

                function closeModal() {
                    var $container = $('.checkout-index-index #payment .payment-option.discount-code._active');
                    if ($container.length) {
                        $container.find('.payment-option-title .action-toggle').trigger('click');
                    }
                }

                function ensureCloseButton() {
                    var $content = $('.checkout-index-index #payment .payment-option.discount-code .payment-option-content');
                    $content.each(function () {
                        var $node = $(this);
                        if (!$node.find('.tekntek-coupon-modal-close').length) {
                            $node.prepend('<button type="button" class="tekntek-coupon-modal-close" aria-label="Close">&times;</button>');
                        }
                    });
                }

                function bindHandlers() {
                    ensureOverlay();
                    ensureCloseButton();
                    syncModalState();

                    $(document).on('click', '.checkout-index-index #payment .payment-option.discount-code .payment-option-title .action-toggle', function () {
                        window.setTimeout(syncModalState, 0);
                    });

                    $(document).on('click', '.' + overlayClass, function () {
                        closeModal();
                        window.setTimeout(syncModalState, 0);
                    });

                    $(document).on('click', '.checkout-index-index #payment .payment-option.discount-code .tekntek-coupon-modal-close', function (event) {
                        event.preventDefault();
                        closeModal();
                        window.setTimeout(syncModalState, 0);
                    });
                }

                function waitForDiscount(attempt) {
                    var $container = $('.checkout-index-index #payment .payment-option.discount-code');
                    if ($container.length) {
                        bindHandlers();
                        return;
                    }

                    if (attempt < maxAttempts) {
                        window.setTimeout(function () {
                            waitForDiscount(attempt + 1);
                        }, 200);
                    }
                }

                waitForDiscount(0);

                return this;
            }
        });
    };
});
