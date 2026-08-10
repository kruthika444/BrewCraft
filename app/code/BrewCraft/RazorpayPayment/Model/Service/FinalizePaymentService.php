<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Model\Service;

use BrewCraft\RazorpayPayment\Gateway\Http\Client\RazorpayClient;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

class FinalizePaymentService
{
    public function __construct(
        private readonly RazorpayClient $razorpayClient,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceService $invoiceService,
        private readonly TransactionFactory $transactionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(
        Order $order,
        string $razorpayOrderId,
        string $razorpayPaymentId
    ): void {
        $gatewayPayment =
            $this->razorpayClient->fetchPayment(
                $razorpayPaymentId
            );

        $this->validateGatewayPayment(
            $order,
            $gatewayPayment,
            $razorpayOrderId,
            $razorpayPaymentId
        );

        $payment = $order->getPayment();

        if (!$payment) {
            throw new LocalizedException(
                __('Magento order payment is missing.')
            );
        }

        /*
         * The Razorpay payment ID is the gateway
         * transaction reference.
         */
        $payment->setTransactionId(
            $razorpayPaymentId
        );

        $payment->setLastTransId(
            $razorpayPaymentId
        );

        $payment->setAdditionalInformation(
            'razorpay_order_id',
            $razorpayOrderId
        );

        $payment->setAdditionalInformation(
            'razorpay_payment_id',
            $razorpayPaymentId
        );

        $payment->setAdditionalInformation(
            'razorpay_status',
            (string) (
                $gatewayPayment['status']
                ?? ''
            )
        );

        $payment->setAdditionalInformation(
            'razorpay_method',
            (string) (
                $gatewayPayment['method']
                ?? ''
            )
        );

        /*
         * Tell Magento that this external
         * transaction represents a capture.
         */
        $payment->setIsTransactionClosed(
            true
        );

        $payment->setShouldCloseParentTransaction(
            true
        );

        $payment->addTransaction(
            \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE
        );

        /*
         * Create invoice only if Magento does not
         * already have one.
         */
        if (
            $order->canInvoice()
            && !$order->hasInvoices()
        ) {
            $invoice = $this->invoiceService
                ->prepareInvoice($order);

            if (!$invoice->getTotalQty()) {
                throw new LocalizedException(
                    __('Unable to create Magento invoice.')
                );
            }

            $invoice->setRequestedCaptureCase(
                Invoice::CAPTURE_OFFLINE
            );

            $invoice->register();

            $invoice->getOrder()->setIsInProcess(
                true
            );

            $transaction = $this
                ->transactionFactory
                ->create();

            $transaction
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
        }

        /*
         * Captured payment means the order can
         * move into Magento processing state.
         */
        if ($order->getState() !== Order::STATE_PROCESSING) {
            $order->setState(
                Order::STATE_PROCESSING
            );

            $order->setStatus(
                Order::STATE_PROCESSING
            );
        }

        $this->orderRepository->save($order);

        $this->logger->info(
            'Razorpay payment finalised in Magento.',
            [
                'order_id' => (int) $order->getEntityId(),
                'increment_id' =>
                    (string) $order->getIncrementId(),
                'razorpay_order_id' =>
                    $razorpayOrderId,
                'razorpay_payment_id' =>
                    $razorpayPaymentId,
                'status' =>
                    $gatewayPayment['status'] ?? '',
            ]
        );
    }

    /**
     * @param array<string, mixed> $gatewayPayment
     */
    private function validateGatewayPayment(
        Order $order,
        array $gatewayPayment,
        string $razorpayOrderId,
        string $razorpayPaymentId
    ): void {
        if (
            (string) ($gatewayPayment['id'] ?? '')
            !== $razorpayPaymentId
        ) {
            throw new LocalizedException(
                __('Razorpay payment reference does not match.')
            );
        }

        if (
            (string) ($gatewayPayment['order_id'] ?? '')
            !== $razorpayOrderId
        ) {
            throw new LocalizedException(
                __('Razorpay order reference does not match.')
            );
        }

        $status = (string) (
            $gatewayPayment['status']
            ?? ''
        );

        $captured = (bool) (
            $gatewayPayment['captured']
            ?? false
        );

        if (
            $status !== 'captured'
            || !$captured
        ) {
            throw new LocalizedException(
                __(
                    'Razorpay payment is not captured. Current status: %1',
                    $status
                )
            );
        }

        $expectedAmount = (int) round(
            (float) $order->getGrandTotal() * 100
        );

        $gatewayAmount = (int) (
            $gatewayPayment['amount']
            ?? 0
        );

        if ($expectedAmount !== $gatewayAmount) {
            throw new LocalizedException(
                __('Razorpay payment amount does not match the Magento order.')
            );
        }

        $gatewayCurrency = strtoupper(
            (string) (
                $gatewayPayment['currency']
                ?? ''
            )
        );

        $orderCurrency = strtoupper(
            (string) $order->getOrderCurrencyCode()
        );

        if ($gatewayCurrency !== $orderCurrency) {
            throw new LocalizedException(
                __('Razorpay payment currency does not match the Magento order.')
            );
        }
    }
}
