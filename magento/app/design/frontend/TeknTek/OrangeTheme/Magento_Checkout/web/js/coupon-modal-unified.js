/**
 * Unified Coupon Modal Handler
 * Shared logic for both Payment and Cart coupon modals
 * 
 * Handles:
 * - Overlay creation and visibility
 * - Modal state synchronization
 * - Click handlers (toggle, overlay, escape key)
 */
define([
    'jquery'
], function ($) {
    'use strict';

    var overlayClass = 'tekntek-coupon-modal-overlay';
    var closeButtonClass = 'tekntek-coupon-modal-close';
    var openClass = 'tekntek-coupon-modal-open';
    var initialized = false;

    /**
     * Ensure overlay element exists in DOM
     */
    function ensureOverlay() {
        if (!$('.' + overlayClass).length) {
            $('body').append('<div class="' + overlayClass + '" aria-hidden="true"></div>');
        }
    }

    /**
     * Sync modal state - check if any modal is open and update body class
     */
    function syncModalState() {
        // Check payment modal state
        var paymentModalOpen = $('#payment .payment-option.discount-code').hasClass('_active') || 
                               $('#payment .payment-option.discount-code').hasClass('active');
        
        // Check cart modal state
        var cartModalOpen = $('#block-discount').hasClass('active');
        
        // Set body class if either modal is open
        var shouldBeOpen = paymentModalOpen || cartModalOpen;
        $('body').toggleClass(openClass, shouldBeOpen);
    }

    /**
     * Close payment modal by triggering toggle
     */
    function closePaymentModal() {
        var $toggle = $('#payment .payment-option.discount-code .payment-option-title .action-toggle');
        if ($toggle.length) {
            $toggle.trigger('click');
        }
    }

    /**
     * Close cart modal by triggering title click
     */
    function closeCartModal() {
        var $title = $('#block-discount .title');
        if ($title.length) {
            $title.trigger('click');
        }
    }

    /**
     * Close all open modals
     */
    function closeAllModals() {
        closePaymentModal();
        closeCartModal();
    }

    /**
     * Ensure close button exists in both modal variants
     */
    function ensureCloseButtons() {
        var buttonHtml = '<button type="button" class="' + closeButtonClass + '" aria-label="Close modal">&times;</button>';

        $('#payment .payment-option.discount-code .payment-option-content').each(function () {
            var $content = $(this);
            if (!$content.find('.' + closeButtonClass).length) {
                $content.prepend(buttonHtml);
            }
        });

        $('#block-discount.active .content').each(function () {
            var $content = $(this);
            if (!$content.find('.' + closeButtonClass).length) {
                $content.prepend(buttonHtml);
            }
        });
    }

    /**
     * Initialize global event handlers (run once)
     */
    function initGlobalHandlers() {
        if (initialized) {
            return;
        }

        ensureOverlay();
        ensureCloseButtons();
        syncModalState();

        // Overlay click - close all modals
        $(document).on('click', '.' + overlayClass, function () {
            closeAllModals();
            window.setTimeout(syncModalState, 0);
        });

        $(document).on('click', '.' + closeButtonClass, function (event) {
            event.preventDefault();
            event.stopPropagation();
            closeAllModals();
            window.setTimeout(syncModalState, 0);
        });

        // ESC key - close all modals
        $(document).on('keyup', function (event) {
            if (event.key === 'Escape') {
                closeAllModals();
                window.setTimeout(syncModalState, 0);
            }
        });

        initialized = true;
    }

    /**
     * Initialize payment modal handlers
     */
    function initPaymentModal() {
        initGlobalHandlers();
        ensureCloseButtons();
        
        // Payment modal toggle handler
        $(document).on('click', '#payment .payment-option.discount-code .payment-option-title .action-toggle', function () {
            window.setTimeout(ensureCloseButtons, 0);
            window.setTimeout(syncModalState, 0);
        });
    }

    /**
     * Initialize cart modal handlers
     */
    function initCartModal() {
        initGlobalHandlers();
        ensureCloseButtons();
        
        // Cart modal toggle handler
        $(document).on('click', '#block-discount .title', function () {
            window.setTimeout(ensureCloseButtons, 0);
            window.setTimeout(syncModalState, 0);
        });
    }

    return {
        initPaymentModal: initPaymentModal,
        initCartModal: initCartModal,
        syncModalState: syncModalState,
        closeAllModals: closeAllModals,
        ensureCloseButtons: ensureCloseButtons
    };
});
