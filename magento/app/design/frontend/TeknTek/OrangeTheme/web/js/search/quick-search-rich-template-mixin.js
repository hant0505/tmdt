define([
    'jquery'
], function ($) {
    'use strict';

    return function (quickSearch) {
        $.widget('mage.quickSearch', quickSearch, {
            options: {
                responseFieldElements: 'ul li',
                template:
                    '<li class="<%- data.row_class %> flex items-center gap-4 p-3 border-b border-gray-100 hover:bg-gray-50" ' +
                        'id="qs-option-<%- data.index %>" role="option" data-url="<%- data.product_url || \"\" %>">' +
                        '<% if (data.product_url) { %>' +
                            '<a class="ttk-autocomplete-link flex items-center gap-4 w-full min-w-0 no-underline" href="<%- data.product_url %>">' +
                        '<% } else { %>' +
                            '<span class="flex items-center gap-4 w-full min-w-0">' +
                        '<% } %>' +
                            '<% if (data.product_image) { %>' +
                                '<span class="flex items-center justify-center w-12 h-12 flex-shrink-0 bg-white">' +
                                    '<img class="w-12 h-12 object-contain" src="<%- data.product_image %>" alt="<%- data.title %>" loading="lazy"/>' +
                                '</span>' +
                            '<% } %>' +
                            '<span class="flex flex-col min-w-0 flex-1">' +
                                '<span class="qs-option-name text-sm font-medium text-gray-900 line-clamp-2"><%- data.title %></span>' +
                                '<% if (data.final_price) { %>' +
                                    '<span class="flex items-center gap-2">' +
                                        '<span class="text-red-600 font-bold"><%- data.final_price %></span>' +
                                        '<% if (data.has_discount || Number(data.regular_price_amount || 0) > Number(data.final_price_amount || 0)) { %>' +
                                            '<span class="text-gray-400 text-xs line-through"><%- data.regular_price %></span>' +
                                        '<% } %>' +
                                    '</span>' +
                                '<% } else if (data.num_results) { %>' +
                                    '<span aria-hidden="true" class="amount"><%- data.num_results %></span>' +
                                '<% } %>' +
                            '</span>' +
                        '<% if (data.product_url) { %>' +
                            '</a>' +
                        '<% } else { %>' +
                            '</span>' +
                        '<% } %>' +
                    '</li>'
            },

            /**
             * Navigate directly when a selected autocomplete row represents a product.
             *
             * @param {Event} e
             * @private
             */
            _onSubmit: function (e) {
                var selected = this.responseList.selected,
                    productUrl = selected && (selected.data('url') || selected.closest('li').data('url'));

                if (productUrl) {
                    e.preventDefault();
                    window.location.href = productUrl;
                    return;
                }

                return this._super(e);
            }
        });

        return $.mage.quickSearch;
    };
});
