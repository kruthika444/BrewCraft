<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Observer;

use BrewCraft\ErpIntegration\Model\Queue\Publisher;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use BrewCraft\ErpIntegration\Logger\Logger;

class OrderPlacedObserver implements ObserverInterface
{
    public function __construct(
        private readonly Publisher $publisher,
        private readonly Logger $logger

    ) {}

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();

        $this->logger->info(
            'Increment ID: ' . $order->getIncrementId()
        );

        $this->publisher->publish(
            (string)$order->getIncrementId()
        );
    }
}
