/**
 * Theme override for Magento captcha reload.
 * Ensures form_key is sent so POST /captcha/refresh passes CSRF validation.
 */
define([
    'jquery',
    'jquery-ui-modules/widget'
], function ($) {
    'use strict';

    $.widget('mage.captcha', {
        options: {
            refreshClass: 'refreshing',
            reloadSelector: '.captcha-reload',
            imageSelector: '.captcha-img',
            imageLoader: ''
        },

        _create: function () {
            this.element.on('click', this.options.reloadSelector, $.proxy(this.refresh, this));
        },

        refresh: function (event) {
            var imageLoader = this.options.imageLoader;
            var formKey = (window.FORM_KEY || $('input[name="form_key"]').first().val() || '');
            var $img = this.element.find(this.options.imageSelector);
            var fallbackSrc;

            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            fallbackSrc = $img.attr('src') || '';
            fallbackSrc = fallbackSrc ? fallbackSrc.replace(/([?&])_=[^&]*/g, '').replace(/[?&]$/, '') : '';

            if (imageLoader) {
                $img.attr('src', imageLoader);
            }
            this.element.addClass(this.options.refreshClass);

            $.ajax({
                url: this.options.url,
                type: 'post',
                dataType: 'json',
                context: this,
                data: {
                    formId: this.options.type,
                    form_key: formKey
                },
                success: function (response) {
                    if (response && response.imgSrc) {
                        $img.attr('src', response.imgSrc + (response.imgSrc.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now());
                        return;
                    }

                    if (fallbackSrc) {
                        $img.attr('src', fallbackSrc + (fallbackSrc.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now());
                    }
                },
                error: function () {
                    if (fallbackSrc) {
                        $img.attr('src', fallbackSrc + (fallbackSrc.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now());
                    }
                },
                complete: function () {
                    this.element.removeClass(this.options.refreshClass);
                }
            });
        }
    });

    return $.mage.captcha;
});
