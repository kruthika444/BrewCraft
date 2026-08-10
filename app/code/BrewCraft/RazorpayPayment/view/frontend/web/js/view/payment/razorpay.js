define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (
    Component,
    rendererList
) {
    'use strict';

    rendererList.push({
        type: 'brewcraft_razorpay',
        component:
            'BrewCraft_RazorpayPayment/js/view/payment/method-renderer/razorpay-method'
    });

    return Component.extend({});
});
