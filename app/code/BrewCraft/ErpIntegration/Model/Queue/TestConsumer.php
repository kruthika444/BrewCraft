<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Queue;

use BrewCraft\ErpIntegration\Logger\Logger;
use Magento\Sales\Model\OrderFactory;
use BrewCraft\ErpIntegration\Model\Service\OrderExportService;

class TestConsumer
{
    public function __construct(
        private readonly Logger $logger,
        private readonly OrderExportService $orderExportService,
        private readonly OrderFactory $orderFactory
    ) {}

    public function process(string $orderIncrementId): void
    {
        $this->logger->info(
            sprintf(
                'Received message from queue: %s',
                $orderIncrementId
            )
        );

        $order = $this->orderFactory
            ->create()
            ->loadByIncrementId($orderIncrementId);

        if (!$order->getId()) {
            $this->logger->error(
                sprintf(
                    'Order %s not found.',
                    $orderIncrementId
                )
            );
            return;
        }

        try {
            $this->orderExportService->export($order);
        } catch (\Throwable $e) {

            $this->logger->error(
                'EXPORT FAILED: ' . $e->getMessage()
            );

            $this->logger->error(
                $e->getTraceAsString()
            );
        }
    }
}
