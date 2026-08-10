<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class ProcessWebhookService
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly FinalizePaymentService $finalizePaymentService,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(
        string $eventId,
        array $payload
    ): void {
        $eventType = trim(
            (string) ($payload['event'] ?? '')
        );

        if ($eventType === '') {
            throw new LocalizedException(
                __('Razorpay webhook event type is missing.')
            );
        }

        $payment = $payload['payload']['payment']['entity']
            ?? [];

        $paymentId = trim(
            (string) ($payment['id'] ?? '')
        );

        $razorpayOrderId = trim(
            (string) ($payment['order_id'] ?? '')
        );

        if (
            $eventType === 'payment.captured'
            && (
                $paymentId === ''
                || $razorpayOrderId === ''
            )
        ) {
            throw new LocalizedException(
                __('Razorpay webhook payment information is incomplete.')
            );
        }

        if (!$this->claimEvent(
            $eventId,
            $eventType,
            $paymentId,
            $razorpayOrderId
        )) {
            /*
             * Duplicate webhook.
             * Already handled or currently being handled.
             */
            return;
        }

        try {
            switch ($eventType) {
                case 'payment.captured':
                    $this->processCapturedPayment(
                        $paymentId,
                        $razorpayOrderId
                    );
                    break;

                case 'payment.failed':
                    /*
                     * For now we log failed payments.
                     * We do not cancel Magento orders automatically.
                     */
                    $this->logger->warning(
                        'Razorpay payment failed webhook received.',
                        [
                            'payment_id' => $paymentId,
                            'razorpay_order_id' =>
                                $razorpayOrderId,
                        ]
                    );
                    break;

                default:
                    /*
                     * Unsupported events are acknowledged
                     * safely but do not change Magento data.
                     */
                    break;
            }

            $this->markEventProcessed(
                $eventId
            );
        } catch (\Throwable $exception) {
            $this->markEventFailed(
                $eventId,
                $exception->getMessage()
            );

            throw $exception;
        }
    }

    private function processCapturedPayment(
        string $paymentId,
        string $razorpayOrderId
    ): void {
        $connection =
            $this->resourceConnection
                ->getConnection();

        $paymentTable =
            $this->resourceConnection
                ->getTableName(
                    'sales_order_payment'
                );

        $orderId = $connection->fetchOne(
            $connection->select()
                ->from(
                    $paymentTable,
                    ['parent_id']
                )
                ->where(
                    'last_trans_id = ?',
                    $paymentId
                )
                ->limit(1)
        );

        if (!$orderId) {
            $this->logger->warning(
                'Razorpay webhook payment has no matching Magento order.',
                [
                    'payment_id' => $paymentId,
                    'razorpay_order_id' =>
                        $razorpayOrderId,
                ]
            );

            return;
        }

        /** @var Order $order */
        $order = $this->orderRepository->get(
            (int) $orderId
        );

        /*
         * If synchronous payment finalization
         * already completed, webhook has nothing
         * else to do.
         */
        if (
            $order->getState()
            === Order::STATE_PROCESSING
            && $order->hasInvoices()
        ) {
            return;
        }

        $this->finalizePaymentService->execute(
            $order,
            $razorpayOrderId,
            $paymentId
        );
    }

    private function claimEvent(
        string $eventId,
        string $eventType,
        string $paymentId,
        string $razorpayOrderId
    ): bool {
        $connection =
            $this->resourceConnection
                ->getConnection();

        $table =
            $this->resourceConnection
                ->getTableName(
                    'brewcraft_razorpay_webhook_event'
                );

        try {
            $connection->insert(
                $table,
                [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'payment_id' =>
                        $paymentId !== ''
                            ? $paymentId
                            : null,
                    'razorpay_order_id' =>
                        $razorpayOrderId !== ''
                            ? $razorpayOrderId
                            : null,
                    'status' => 'processing',
                ]
            );

            return true;
        } catch (\Throwable $exception) {
            /*
             * Check if duplicate event ID exists.
             */
            $existing = $connection->fetchOne(
                $connection->select()
                    ->from(
                        $table,
                        ['entity_id']
                    )
                    ->where(
                        'event_id = ?',
                        $eventId
                    )
                    ->limit(1)
            );

            if ($existing) {
                return false;
            }

            throw $exception;
        }
    }

    private function markEventProcessed(
        string $eventId
    ): void {
        $connection =
            $this->resourceConnection
                ->getConnection();

        $table =
            $this->resourceConnection
                ->getTableName(
                    'brewcraft_razorpay_webhook_event'
                );

        $connection->update(
            $table,
            [
                'status' => 'processed',
                'processed_at' =>
                    $this->dateTime->gmtDate(),
                'error_message' => null,
            ],
            [
                'event_id = ?' => $eventId,
            ]
        );
    }

    private function markEventFailed(
        string $eventId,
        string $message
    ): void {
        $connection =
            $this->resourceConnection
                ->getConnection();

        $table =
            $this->resourceConnection
                ->getTableName(
                    'brewcraft_razorpay_webhook_event'
                );

        $connection->update(
            $table,
            [
                'status' => 'failed',
                'error_message' =>
                    mb_substr(
                        $message,
                        0,
                        65535
                    ),
            ],
            [
                'event_id = ?' => $eventId,
            ]
        );
    }
}
