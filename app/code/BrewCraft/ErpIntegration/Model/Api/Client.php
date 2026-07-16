<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Api;

use BrewCraft\ErpIntegration\Helper\Config;
use BrewCraft\ErpIntegration\Logger\Logger;
use Magento\Framework\HTTP\Client\Curl;

class Client
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly Logger $logger
    ) {}

    private function get(string $endpoint): string
    {
        $url = rtrim($this->config->getBaseUrl(), '/')
            . '/api/'
            . $this->config->getApiVersion()
            . '/'
            . ltrim($endpoint, '/');

        $this->curl->setTimeout($this->config->getTimeout());

        $this->logger->info('Calling ERP');
        $this->logger->info($url);

        $this->curl->get($url);

        $response = $this->curl->getBody();

        $this->logger->info('ERP Response');
        $this->logger->info($response);

        return $response;
    }

    public function getProducts(): string
    {
        return $this->get('products');
    }

    public function getInventory(): string
    {
        return $this->get('inventory');
    }

    public function getPrices(): string
    {
        return $this->get('prices');
    }
}
