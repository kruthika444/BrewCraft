define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Ui/js/model/messageList',
    'mage/url',
    'mage/storage'
], function (
    $,
    Component,
    quote,
    fullScreenLoader,
    messageList,
    urlBuilder,
    storage
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'BrewCraft_RazorpayPayment/payment/razorpay'
        },

        isProcessing: false,

        getCode: function () {
            return 'brewcraft_razorpay';
        },

        isActive: function () {
            return true;
        },

        startRazorpayPayment: function () {
            var self = this;

            if (self.isProcessing) {
                return;
            }

            self.isProcessing = true;
            fullScreenLoader.startLoader();

            self.loadRazorpayScript()
                .done(function () {
                    self.createRazorpayOrder();
                })
                .fail(function () {
                    self.isProcessing = false;
                    fullScreenLoader.stopLoader();

                    messageList.addErrorMessage({
                        message: 'Unable to load Razorpay Checkout.'
                    });
                });
        },

        /**
         * Load Razorpay checkout.js only when needed.
         */
        loadRazorpayScript: function () {
            var deferred = $.Deferred(),
                script;

            if (window.Razorpay) {
                deferred.resolve();

                return deferred.promise();
            }

            script = document.createElement('script');

            script.src =
                'https://checkout.razorpay.com/v1/checkout.js';

            script.async = true;

            script.onload = function () {
                if (window.Razorpay) {
                    deferred.resolve();
                } else {
                    deferred.reject();
                }
            };

            script.onerror = function () {
                deferred.reject();
            };

            document.head.appendChild(script);

            return deferred.promise();
        },

        /**
         * Ask Magento backend to create the Razorpay Order.
         */
        createRazorpayOrder: function () {
            var self = this;

            storage.post(
                urlBuilder.build(
                    'razorpay/payment/createOrder'
                ),
                JSON.stringify({}),
                false
            ).done(function (response) {
                fullScreenLoader.stopLoader();

                if (
                    !response.success ||
                    !response.order
                ) {
                    self.isProcessing = false;

                    messageList.addErrorMessage({
                        message:
                            response.message ||
                            'Unable to initialise Razorpay payment.'
                    });

                    return;
                }

                self.openRazorpayCheckout(
                    response.order
                );
            }).fail(function (response) {
                fullScreenLoader.stopLoader();
                self.isProcessing = false;

                var message =
                    'Unable to initialise Razorpay payment.';

                if (
                    response.responseJSON &&
                    response.responseJSON.message
                ) {
                    message =
                        response.responseJSON.message;
                }

                messageList.addErrorMessage({
                    message: message
                });
            });
        },

        openRazorpayCheckout: function (orderData) {
            var self = this,
                config =
                    window.checkoutConfig.payment
                        .brewcraft_razorpay || {},
                billingAddress =
                    quote.billingAddress(),
                customerName = '',
                customerEmail = '',
                customerPhone = '',
                razorpay;

            if (quote.guestEmail) {
                customerEmail = quote.guestEmail;
            }

            if (billingAddress) {
                customerName = [
                    billingAddress.firstname || '',
                    billingAddress.lastname || ''
                ].join(' ').trim();

                customerPhone =
                    billingAddress.telephone || '';
            }

            try {
                razorpay = new window.Razorpay({
                    key: config.key_id,

                    amount: orderData.amount,

                    currency:
                        orderData.currency,

                    name:
                        config.store_name ||
                        'BrewCraft',

                    description:
                        'BrewCraft Order Payment',

                    order_id:
                        orderData.razorpay_order_id,

                    prefill: {
                        name: customerName,
                        email: customerEmail,
                        contact: customerPhone
                    },

                    notes: {
                        receipt:
                            orderData.receipt || ''
                    },

                    handler: function (response) {
                        self.handlePaymentSuccess(
                            response,
                            orderData
                        );
                    },

                    modal: {
                        ondismiss: function () {
                            self.isProcessing = false;

                            messageList.addNoticeMessage({
                                message:
                                    'Razorpay payment was cancelled.'
                            });
                        }
                    }
                });

                razorpay.on(
                    'payment.failed',
                    function (response) {
                        self.handlePaymentFailure(
                            response
                        );
                    }
                );

                razorpay.open();
            } catch (error) {
                self.isProcessing = false;

                messageList.addErrorMessage({
                    message:
                        'Unable to open Razorpay Checkout.'
                });

                console.error(
                    'Razorpay Checkout Error:',
                    error
                );
            }
        },

        handlePaymentSuccess: function (
            response,
            orderData
        ) {
            var self = this;

            fullScreenLoader.startLoader();

            storage.post(
                urlBuilder.build(
                    'razorpay/payment/verify'
                ),
                JSON.stringify({
                    razorpay_payment_id:
                        response.razorpay_payment_id,

                    razorpay_order_id:
                        response.razorpay_order_id,

                    razorpay_signature:
                        response.razorpay_signature
                }),
                false
            ).done(function (verifyResponse) {
                fullScreenLoader.stopLoader();

                if (!verifyResponse.success) {
                    self.isProcessing = false;

                    messageList.addErrorMessage({
                        message:
                            verifyResponse.message ||
                            'Payment verification failed.'
                    });

                    return;
                }

                /*
                 * Payment verified and Magento order created.
                 */
                window.location.href =
                    urlBuilder.build(
                        'checkout/onepage/success'
                    );
            }).fail(function (xhr) {
                fullScreenLoader.stopLoader();
                self.isProcessing = false;

                var message =
                    'Unable to verify Razorpay payment.';

                if (
                    xhr.responseJSON &&
                    xhr.responseJSON.message
                ) {
                    message =
                        xhr.responseJSON.message;
                }

                messageList.addErrorMessage({
                    message: message
                });
            });
        },

        handlePaymentFailure: function (
            response
        ) {
            this.isProcessing = false;

            var message =
                'Razorpay payment failed.';

            if (
                response.error &&
                response.error.description
            ) {
                message =
                    response.error.description;
            }

            messageList.addErrorMessage({
                message: message
            });

            console.error(
                'Razorpay payment failure:',
                response
            );
        }
    });
});
