<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Api\JobRepositoryInterface;
use BrewCraft\ErpIntegration\Helper\Config;
use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Api\OrderClient;
use BrewCraft\ErpIntegration\Model\JobFactory;
use Magento\Sales\Model\Order;

class OrderExportService
{
    public function __construct(
        private readonly OrderClient $client,
        private readonly Logger $logger,
        private readonly JobRepositoryInterface $jobRepository,
        private readonly JobFactory $jobFactory,
        private readonly Config $config
    ) {}

    /**
     * Export a Magento order to ERP.
     */
    public function export(Order $order): void
    {
        $storeId = (int)$order->getStoreId();

        if (!$this->config->isEnabled($storeId)) {
            $this->logger->info(
                sprintf(
                    'Order %s export skipped because ERP integration is disabled.',
                    $order->getIncrementId()
                )
            );

            return;
        }

        if (!$this->config->isOrderExportEnabled($storeId)) {
            $this->logger->info(
                sprintf(
                    'Order %s export skipped because order export is disabled.',
                    $order->getIncrementId()
                )
            );

            return;
        }

        $startTime = microtime(true);

        $maxAttempts = $this->config->getRetryAttempts($storeId);
        $retryDelay = $this->config->getRetryDelay($storeId);

        $payload = [
            'header' => $this->buildHeader($order),
            'customer' => $this->buildCustomer($order),
            'billing_address' => $this->buildBillingAddress($order),
            'shipping_address' => $this->buildShippingAddress($order),
            'payment' => $this->buildPayment($order),
            'shipping' => $this->buildShipping($order),
            'totals' => $this->buildTotals($order),
            'items' => $this->buildItems($order)
        ];

        try {
            $attemptsUsed = $this->exportWithRetry(
                order: $order,
                payload: $payload,
                maxAttempts: $maxAttempts,
                retryDelay: $retryDelay
            );

            $executionTime = microtime(true) - $startTime;

            $message = sprintf(
                'Order %s exported successfully after %d attempt(s).',
                $order->getIncrementId(),
                $attemptsUsed
            );

            $this->saveJob(
                status: 'SUCCESS',
                recordsProcessed: 1,
                executionTime: $executionTime,
                message: $message
            );

            $this->logger->info($message);
        } catch (\Throwable $exception) {
            $executionTime = microtime(true) - $startTime;

            $message = sprintf(
                'Order %s export permanently failed after %d attempt(s). Error: %s',
                $order->getIncrementId(),
                $maxAttempts,
                $exception->getMessage()
            );

            $this->saveJob(
                status: 'FAILED',
                recordsProcessed: 0,
                executionTime: $executionTime,
                message: $message
            );

            $this->logger->critical($message);

            /*
             * Do not rethrow the exception.
             *
             * All configured retry attempts have already been completed.
             * The final failure has been stored in brewcraft_sync_job.
             *
             * Rethrowing here would mark queue processing as failed and may
             * cause the same queue message to be processed repeatedly.
             */
        }
    }

    /**
     * Attempt to export an order with configurable retries.
     */
    private function exportWithRetry(
        Order $order,
        array $payload,
        int $maxAttempts,
        int $retryDelay
    ): int {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $this->logger->info(
                    sprintf(
                        'Export attempt %d/%d for order %s.',
                        $attempt,
                        $maxAttempts,
                        $order->getIncrementId()
                    )
                );

                $this->client->exportOrder($payload);

                return $attempt;
            } catch (\Throwable $exception) {
                $lastException = $exception;

                $this->logger->warning(
                    sprintf(
                        'Export attempt %d/%d failed for order %s: %s',
                        $attempt,
                        $maxAttempts,
                        $order->getIncrementId(),
                        $exception->getMessage()
                    )
                );

                if ($attempt < $maxAttempts && $retryDelay > 0) {
                    $this->logger->info(
                        sprintf(
                            'Waiting %d second(s) before retrying order %s.',
                            $retryDelay,
                            $order->getIncrementId()
                        )
                    );

                    sleep($retryDelay);
                }
            }
        }

        throw new \RuntimeException(
            sprintf(
                'ERP export failed after %d attempt(s). Last error: %s',
                $maxAttempts,
                $lastException?->getMessage() ?? 'Unknown ERP error'
            ),
            0,
            $lastException
        );
    }

    /**
     * Save synchronization history.
     */
    private function saveJob(
        string $status,
        int $recordsProcessed,
        float $executionTime,
        string $message
    ): void {
        try {
            $job = $this->jobFactory->create();

            $job->setData([
                'job_type' => 'ORDER_EXPORT',
                'status' => $status,
                'records_processed' => $recordsProcessed,
                'execution_time' => $executionTime,
                'message' => $message
            ]);

            $this->jobRepository->save($job);
        } catch (\Throwable $exception) {
            /*
             * A sync-history database failure should be logged separately.
             * It should not trigger another ERP export attempt.
             */
            $this->logger->error(
                sprintf(
                    'Unable to save ORDER_EXPORT sync history: %s',
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Build ERP order header.
     */
    private function buildHeader(Order $order): array
    {
        return [
            'order_number' => $order->getIncrementId(),
            'order_date' => $order->getCreatedAt(),
            'status' => $order->getStatus(),
            'currency' => $order->getOrderCurrencyCode()
        ];
    }

    /**
     * Build ERP customer information.
     */
    private function buildCustomer(Order $order): array
    {
        return [
            'firstname' => $order->getCustomerFirstname(),
            'lastname' => $order->getCustomerLastname(),
            'email' => $order->getCustomerEmail(),
            'group_id' => (int)$order->getCustomerGroupId()
        ];
    }

    /**
     * Build ERP billing address.
     */
    private function buildBillingAddress(Order $order): array
    {
        $billingAddress = $order->getBillingAddress();

        if (!$billingAddress) {
            return [];
        }

        return [
            'street' => implode(' ', $billingAddress->getStreet()),
            'city' => $billingAddress->getCity(),
            'region' => $billingAddress->getRegion(),
            'postcode' => $billingAddress->getPostcode(),
            'country' => $billingAddress->getCountryId(),
            'telephone' => $billingAddress->getTelephone()
        ];
    }

    /**
     * Build ERP shipping address.
     */
    private function buildShippingAddress(Order $order): array
    {
        $shippingAddress = $order->getShippingAddress();

        if (!$shippingAddress) {
            return [];
        }

        return [
            'street' => implode(' ', $shippingAddress->getStreet()),
            'city' => $shippingAddress->getCity(),
            'region' => $shippingAddress->getRegion(),
            'postcode' => $shippingAddress->getPostcode(),
            'country' => $shippingAddress->getCountryId(),
            'telephone' => $shippingAddress->getTelephone()
        ];
    }

    /**
     * Build ERP payment information.
     */
    private function buildPayment(Order $order): array
    {
        $payment = $order->getPayment();

        if (!$payment) {
            return [];
        }

        return [
            'method' => $payment->getMethod()
        ];
    }

    /**
     * Build ERP shipping information.
     */
    private function buildShipping(Order $order): array
    {
        return [
            'method' => $order->getShippingMethod(),
            'description' => $order->getShippingDescription(),
            'amount' => (float)$order->getShippingAmount()
        ];
    }

    /**
     * Build ERP order totals.
     */
    private function buildTotals(Order $order): array
    {
        return [
            'subtotal' => (float)$order->getSubtotal(),
            'discount' => (float)$order->getDiscountAmount(),
            'tax' => (float)$order->getTaxAmount(),
            'shipping' => (float)$order->getShippingAmount(),
            'grand_total' => (float)$order->getGrandTotal()
        ];
    }

    /**
     * Build ERP order items.
     */
    private function buildItems(Order $order): array
    {
        $items = [];

        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'qty' => (float)$item->getQtyOrdered(),
                'price' => (float)$item->getPrice(),
                'row_total' => (float)$item->getRowTotal(),
                'tax_amount' => (float)$item->getTaxAmount()
            ];
        }

        return $items;
    }
}
