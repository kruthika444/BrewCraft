require([
    'jquery',
    'domReady!'
], function ($) {
    'use strict';

    var placeholder = null;
    var observer = null;
    var scheduled = false;

    /**
     * Is Magento currently showing the Shipping step?
     */
    function isShippingStepVisible() {
        var shippingStep = document.querySelector('#shipping');

        if (!shippingStep) {
            return false;
        }

        return window.getComputedStyle(shippingStep).display !== 'none';
    }

    /**
     * Move Magento's EXISTING shipping-method block.
     *
     * We are not cloning it.
     * We are not rebuilding it.
     * Knockout bindings stay attached.
     */
    function updateShippingMethodPosition() {
        var shippingMethod = document.querySelector(
                '.checkout-shipping-method'
            ),
            summary = document.querySelector(
                '.opc-sidebar .opc-block-summary'
            );

        if (!shippingMethod || !summary) {
            return;
        }

        /**
         * Save original position only once.
         */
        if (!placeholder) {
            placeholder = document.createComment(
                'brewcraft-shipping-method-original-position'
            );

            shippingMethod.parentNode.insertBefore(
                placeholder,
                shippingMethod
            );
        }

        /**
         * SHIPPING STEP:
         * move underneath Order Summary.
         */
        if (isShippingStepVisible()) {

            if (
                shippingMethod.previousElementSibling !== summary ||
                shippingMethod.parentNode !== summary.parentNode
            ) {
                summary.insertAdjacentElement(
                    'afterend',
                    shippingMethod
                );
            }

            shippingMethod.classList.add(
                'brewcraft-shipping-method--sidebar'
            );

            return;
        }

        /**
         * PAYMENT STEP:
         * restore it to Magento's original position.
         *
         * Magento can then control it normally.
         */
        if (
            placeholder &&
            placeholder.parentNode &&
            shippingMethod.parentNode !== placeholder.parentNode
        ) {
            placeholder.parentNode.insertBefore(
                shippingMethod,
                placeholder.nextSibling
            );
        }

        shippingMethod.classList.remove(
            'brewcraft-shipping-method--sidebar'
        );
    }

    /**
     * Avoid running repeatedly during one Knockout render cycle.
     */
    function scheduleUpdate() {
        if (scheduled) {
            return;
        }

        scheduled = true;

        window.requestAnimationFrame(function () {
            scheduled = false;

            updateShippingMethodPosition();
        });
    }

    /**
     * Initial run.
     */
    scheduleUpdate();

    /**
     * Magento Checkout renders/re-renders through Knockout,
     * therefore observe the checkout DOM.
     */
    observer = new MutationObserver(function () {
        scheduleUpdate();
    });

    observer.observe(
        document.body,
        {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: [
                'class',
                'style'
            ]
        }
    );

    /**
     * Shipping -> Payment hash change.
     */
    $(window).on(
        'hashchange',
        scheduleUpdate
    );
});
