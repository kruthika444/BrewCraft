define([
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'ko'
], function (
    Component,
    customerData,
    ko
) {
    'use strict';

    return Component.extend({
        defaults: {
            template:
                'BrewCraft_RequestQuote/minicart/quote-action'
        },

        /**
         * Initialize customer-data observables.
         *
         * @returns {Object}
         */
        initialize: function () {
            this._super();

            this.eligibility = customerData.get(
                'brewcraft_quote_eligibility'
            );

            this.cart = customerData.get('cart');

            return this;
        },

        /**
         * Check whether the RFQ action should be displayed.
         *
         * @returns {Boolean}
         */
        canRequestQuote: function () {
            var eligibility = this.eligibility() || {};
            var cart = this.cart() || {};
            var itemCount = Number(
                cart.summary_count || 0
            );

            return eligibility.can_request_quote === true
                && itemCount > 0
                && Boolean(
                    eligibility.request_quote_url
                );
        },

        /**
         * Return the Request Quote form URL.
         *
         * @returns {String}
         */
        getRequestQuoteUrl: function () {
            var eligibility = this.eligibility() || {};

            return eligibility.request_quote_url || '';
        }
    });
});
