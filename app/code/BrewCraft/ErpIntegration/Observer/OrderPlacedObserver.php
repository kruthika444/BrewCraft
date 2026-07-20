<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Observer;

use BrewCraft\ErpIntegration\Helper\Config;
use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Queue\Publisher;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class OrderPlacedObserver implements ObserverInterface
{
    public function __construct(
        private readonly Publisher $publisher,
        private readonly Logger $logger,
        private readonly Config $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Order|null $order */
        $order = $observer->getEvent()->getOrder();


        $storeId = (int)$order->getStoreId();
        $incrementId = (string)$order->getIncrementId();

        if (!$this->config->isEnabled($storeId)) {
            $this->logger->info(
                sprintf(
                    'Order %s was not published because ERP integration is disabled.',
                    $incrementId
                )
            );

            return;
        }

        if (!$this->config->isOrderExportEnabled($storeId)) {
            $this->logger->info(
                sprintf(
                    'Order %s was not published because order export is disabled.',
                    $incrementId
                )
            );

            return;
        }

        if (!$this->config->isQueueEnabled($storeId)) {
            $this->logger->info(
                sprintf(
                    'Order %s was not published because queue processing is disabled.',
                    $incrementId
                )
            );

            return;
        }

        $this->logger->info(
            sprintf(
                'Increment ID: %s',
                $incrementId
            )
        );

        $this->publisher->publish($incrementId);
    }
}
