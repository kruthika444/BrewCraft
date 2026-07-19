<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;
use BrewCraft\ErpIntegration\Logger\Logger;

class Publisher
{
    private const TOPIC = 'brewcraft.order.export';

    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly Logger $logger

    ) {}

    public function publish(string $message): void
    {
        $this->logger->info(
            sprintf(
                'Publishing message to queue: %s',
                $message
            )
        );
        
        $this->publisher->publish(
            self::TOPIC,
            $message
        );
    }
}
