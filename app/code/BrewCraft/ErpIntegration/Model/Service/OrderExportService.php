<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Api\OrderClient;
use Magento\Sales\Model\Order;
use BrewCraft\ErpIntegration\Api\JobRepositoryInterface;
use BrewCraft\ErpIntegration\Model\JobFactory;

class OrderExportService
{
    public function __construct(
        private readonly OrderClient $client,
        private readonly Logger $logger,
        private readonly JobRepositoryInterface $jobRepository,
        private readonly JobFactory $jobFactory
    ) {}

    public function export(Order $order): void
    {
        $startTime = microtime(true);
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

            $this->client->exportOrder($payload);

            $executionTime = microtime(true) - $startTime;

            $job = $this->jobFactory->create();

            $job->setData([
                'job_type' => 'ORDER_EXPORT',
                'status' => 'SUCCESS',
                'records_processed' => 1,
                'execution_time' => $executionTime,
                'message' => sprintf(
                    'Order %s exported successfully.',
                    $order->getIncrementId()
                )
            ]);

            $this->jobRepository->save($job);

            $this->logger->info(
                sprintf(
                    'Order %s exported successfully.',
                    $order->getIncrementId()
                )
            );
        } catch (\Throwable $e) {

            $executionTime = microtime(true) - $startTime;

            $job = $this->jobFactory->create();

            $job->setData([
                'job_type' => 'ORDER_EXPORT',
                'status' => 'FAILED',
                'records_processed' => 0,
                'execution_time' => $executionTime,
                'message' => $e->getMessage()
            ]);

            $this->jobRepository->save($job);

            $this->logger->error($e->getMessage());

            throw $e;
        }
    }

    /**
     * Build ERP Order Header
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
     * Build ERP Customer Information
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
     * Build ERP Billing Address
     */
    private function buildBillingAddress(Order $order): array
    {
        $billing = $order->getBillingAddress();

        if (!$billing) {
            return [];
        }

        return [
            'street' => implode(' ', $billing->getStreet()),
            'city' => $billing->getCity(),
            'region' => $billing->getRegion(),
            'postcode' => $billing->getPostcode(),
            'country' => $billing->getCountryId(),
            'telephone' => $billing->getTelephone()
        ];
    }

    /**
     * Build ERP Shipping Address
     */
    private function buildShippingAddress(Order $order): array
    {
        $shipping = $order->getShippingAddress();

        if (!$shipping) {
            return [];
        }

        return [
            'street' => implode(' ', $shipping->getStreet()),
            'city' => $shipping->getCity(),
            'region' => $shipping->getRegion(),
            'postcode' => $shipping->getPostcode(),
            'country' => $shipping->getCountryId(),
            'telephone' => $shipping->getTelephone()
        ];
    }

    /**
     * Build ERP Payment Information
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
     * Build ERP Shipping Information
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
     * Build ERP Order Totals
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
     * Build ERP Order Items
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
