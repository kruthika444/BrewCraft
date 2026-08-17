define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        var $form = $(element),
            $output = $form.find('[data-role="quote-estimate"]');

        if (!$output.length) {
            return;
        }

        function calculateEstimate() {
            var total = 0;

            $form.find('[data-quote-product]').each(function () {
                var $row = $(this),

                    currentPrice = parseFloat(
                        $row.attr('data-current-price')
                    ) || 0,

                    requestedQty = parseFloat(
                        $row.find('[data-role="requested-qty"]').val()
                    ) || 0,

                    expectedPriceRaw = $row
                        .find('[data-role="expected-price"]')
                        .val(),

                    expectedPrice = parseFloat(expectedPriceRaw),

                    unitPrice;

                /*
                 * Expected price entered:
                 * use requested qty × expected price.
                 *
                 * Expected price blank:
                 * use requested qty × current Magento price.
                 */
                if (
                    expectedPriceRaw !== '' &&
                    !isNaN(expectedPrice) &&
                    expectedPrice > 0
                ) {
                    unitPrice = expectedPrice;
                } else {
                    unitPrice = currentPrice;
                }

                total += requestedQty * unitPrice;
            });

            $output.text(
                '₹' +
                total.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })
            );
        }

        /*
         * Update immediately while the customer types.
         */
        $form.on(
            'input change',
            '[data-role="requested-qty"], [data-role="expected-price"]',
            calculateEstimate
        );

        /*
         * Calculate initial value on page load.
         */
        calculateEstimate();
    };
});
