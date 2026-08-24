define([
    'jquery'
], function ($) {
    'use strict';

    function getQtyInput($button) {
        return $button
            .closest('.brewcraft-cart-qty')
            .find('input[data-role="cart-item-qty"]')
            .first();
    }

    function getCurrentQty($input) {
        var qty = parseFloat($input.val());

        if (isNaN(qty) || qty < 1) {
            qty = 1;
        }

        return qty;
    }

    function setQty($input, qty) {
        var min = parseFloat($input.attr('min'));

        if (isNaN(min)) {
            min = 1;
        }

        if (qty < min) {
            qty = min;
        }

        $input
            .val(qty)
            .trigger('change');
    }


    //
    // Minus
    //
    $(document).on(
        'click',
        '[data-role="cart-qty-minus"]',
        function (event) {
            event.preventDefault();

            var $button = $(this);
            var $input = getQtyInput($button);

            if (!$input.length) {
                return;
            }

            var currentQty = getCurrentQty($input);

            setQty(
                $input,
                currentQty - 1
            );
        }
    );


    //
    // Plus
    //
    $(document).on(
        'click',
        '[data-role="cart-qty-plus"]',
        function (event) {
            event.preventDefault();

            var $button = $(this);
            var $input = getQtyInput($button);

            if (!$input.length) {
                return;
            }

            var currentQty = getCurrentQty($input);

            setQty(
                $input,
                currentQty + 1
            );
        }
    );

});
