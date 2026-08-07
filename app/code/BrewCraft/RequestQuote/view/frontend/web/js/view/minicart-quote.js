define([
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'jquery'
], function (
    Component,
    customerData,
    $
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'BrewCraft_RequestQuote/minicart/quote-action'
        },

        initialize: function () {
            this._super();

            this.eligibility = customerData.get(
                'brewcraft_quote_eligibility'
            );
            this.cart = customerData.get('cart');

            this.applyAcceptedQuoteState();

            this.eligibility.subscribe(
                this.applyAcceptedQuoteState.bind(this)
            );

            return this;
        },

        canRequestQuote: function () {
            var eligibility = this.eligibility() || {};
            var cart = this.cart() || {};
            var itemCount = Number(cart.summary_count || 0);

            return eligibility.can_request_quote === true
                && eligibility.is_accepted_quote_cart !== true
                && itemCount > 0
                && Boolean(eligibility.request_quote_url);
        },

        isAcceptedQuoteCart: function () {
            var eligibility = this.eligibility() || {};

            return eligibility.is_accepted_quote_cart === true;
        },

        getRequestQuoteUrl: function () {
            var eligibility = this.eligibility() || {};

            return eligibility.request_quote_url || '';
        },

        applyAcceptedQuoteState: function () {
            $('body').toggleClass(
                'brewcraft-accepted-quote-cart',
                this.isAcceptedQuoteCart()
            );
        }
    });
});
