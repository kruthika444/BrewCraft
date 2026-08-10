<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Model\Service;

use BrewCraft\RazorpayPayment\Model\Payment\Method;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use BrewCraft\RazorpayPayment\Model\Service\FinalizePaymentService;

class VerifyAndPlaceOrderService
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly PaymentSignatureVerifier $signatureVerifier,
        private readonly LoggerInterface $logger,
        private readonly FinalizePaymentService $finalizePaymentService,
        private readonly OrderRepositoryInterface $orderRepository
    ) {}

    /**
     * @return array{
     *     order_id:int,
     *     increment_id:string
     * }
     */
    public function execute(
        string $razorpayPaymentId,
        string $browserRazorpayOrderId,
        string $razorpaySignature
    ): array {
        $quote = $this->checkoutSession->getQuote();

        if (!$quote->getId()) {
            throw new LocalizedException(
                __('Unable to find the checkout cart.')
            );
        }

        if (!$quote->getIsActive()) {
            throw new LocalizedException(
                __('This shopping cart is no longer active.')
            );
        }

        $storedRazorpayOrderId = trim(
            (string) $quote->getData(
                'brewcraft_razorpay_order_id'
            )
        );

        if ($storedRazorpayOrderId === '') {
            throw new LocalizedException(
                __('Razorpay order information is missing.')
            );
        }

        /*
         * Compare browser order ID with our server copy.
         *
         * This is NOT the signature verification yet.
         * It is an additional consistency check.
         */
        if (
            !hash_equals(
                $storedRazorpayOrderId,
                $browserRazorpayOrderId
            )
        ) {
            throw new LocalizedException(
                __('Razorpay order reference does not match.')
            );
        }

        $isValid =
            $this->signatureVerifier->verify(
                $storedRazorpayOrderId,
                $razorpayPaymentId,
                $razorpaySignature
            );

        if (!$isValid) {
            $this->logger->warning(
                'Razorpay payment signature verification failed.',
                [
                    'quote_id' => (int) $quote->getId(),
                    'razorpay_order_id' =>
                    $storedRazorpayOrderId,
                    'razorpay_payment_id' =>
                    $razorpayPaymentId,
                ]
            );

            throw new LocalizedException(
                __('Razorpay payment verification failed.')
            );
        }

        /*
         * Verification succeeded.
         *
         * Persist gateway references before
         * converting the quote into an order.
         */
        $quote->setData(
            'brewcraft_razorpay_payment_id',
            $razorpayPaymentId
        );

        $quote->setData(
            'brewcraft_razorpay_signature',
            $razorpaySignature
        );

        $payment = $quote->getPayment();

        $payment->setMethod(
            Method::CODE
        );

        /*
         * Store Razorpay data in quote payment
         * additional information.
         *
         * Magento copies payment information when
         * converting quote → sales order.
         */
        $payment->setAdditionalInformation(
            'razorpay_order_id',
            $storedRazorpayOrderId
        );

        $payment->setAdditionalInformation(
            'razorpay_payment_id',
            $razorpayPaymentId
        );

        $payment->setAdditionalInformation(
            'razorpay_signature',
            $razorpaySignature
        );

        $this->cartRepository->save($quote);

        /*
         * Convert Magento quote/cart into
         * Magento sales order.
         */
        $orderId = (int) $this->cartManagement
            ->placeOrder(
                (int) $quote->getId()
            );

        $order = $this->orderRepository->get(
            $orderId
        );

        $this->finalizePaymentService->execute(
            $order,
            $storedRazorpayOrderId,
            $razorpayPaymentId
        );

        if ($orderId <= 0) {
            throw new LocalizedException(
                __('Magento could not place the order.')
            );
        }

        /*
         * Checkout session uses this information
         * for the success page.
         */
        $order = $this->checkoutSession
            ->getLastRealOrder();

        $incrementId = (string) $order
            ->getIncrementId();

        $this->logger->info(
            'Razorpay payment verified and Magento order placed.',
            [
                'quote_id' => (int) $quote->getId(),
                'order_id' => $orderId,
                'increment_id' => $incrementId,
                'razorpay_order_id' =>
                $storedRazorpayOrderId,
                'razorpay_payment_id' =>
                $razorpayPaymentId,
            ]
        );

        return [
            'order_id' => $orderId,
            'increment_id' => $incrementId,
        ];
    }
}
