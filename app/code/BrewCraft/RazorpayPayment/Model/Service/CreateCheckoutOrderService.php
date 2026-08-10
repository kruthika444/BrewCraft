<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Model\Service;

use BrewCraft\RazorpayPayment\Gateway\Http\Client\RazorpayClient;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class CreateCheckoutOrderService
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly RazorpayClient $razorpayClient,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $quote = $this->checkoutSession->getQuote();

        if (!$quote->getId()) {
            throw new LocalizedException(
                __('Unable to find an active shopping cart.')
            );
        }

        if (!$quote->getItemsCount()) {
            throw new LocalizedException(
                __('Your shopping cart is empty.')
            );
        }

        $quote->collectTotals();

        $grandTotal = (float) $quote->getGrandTotal();

        if ($grandTotal <= 0) {
            throw new LocalizedException(
                __('The order total must be greater than zero.')
            );
        }

        $currency = (string) $quote->getQuoteCurrencyCode();

        if ($currency === '') {
            throw new LocalizedException(
                __('Unable to determine the checkout currency.')
            );
        }

        $amountInSubunits = (int) round(
            $grandTotal * 100
        );

        $receipt = sprintf(
            'quote_%d_%s',
            (int) $quote->getId(),
            time()
        );

        $notes = [
            'source' => 'brewcraft_magento_checkout',
            'magento_quote_id' => (string) $quote->getId(),
        ];

        if ($quote->getCustomerId()) {
            $notes['customer_id'] =
                (string) $quote->getCustomerId();
        }

        $this->logger->info(
            'BrewCraft Razorpay checkout amount debug.',
            [
                'quote_id' => (int) $quote->getId(),
                'grand_total' => $grandTotal,
                'currency' => $currency,
                'amount_in_subunits' => $amountInSubunits,
            ]
        );

        $razorpayOrder =
            $this->razorpayClient->createOrder(
                $amountInSubunits,
                $currency,
                $receipt,
                $notes
            );

        $razorpayOrderId =
            (string) $razorpayOrder['id'];

        /*
         * Store the gateway order ID server-side.
         *
         * We will later use THIS value for
         * payment-signature verification.
         */
        $quote->setData(
            'brewcraft_razorpay_order_id',
            $razorpayOrderId
        );

        $quote->setData(
            'brewcraft_razorpay_payment_id',
            null
        );

        $quote->setData(
            'brewcraft_razorpay_signature',
            null
        );

        $this->cartRepository->save($quote);

        return [
            'razorpay_order_id' =>
                $razorpayOrderId,

            'amount' =>
                (int) $razorpayOrder['amount'],

            'currency' =>
                (string) $razorpayOrder['currency'],

            'receipt' =>
                (string) (
                    $razorpayOrder['receipt']
                    ?? $receipt
                ),
        ];
    }
}
