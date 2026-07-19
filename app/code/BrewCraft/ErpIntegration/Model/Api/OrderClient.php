<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Api;

use BrewCraft\ErpIntegration\Helper\Config;
use BrewCraft\ErpIntegration\Logger\Logger;
use Magento\Framework\HTTP\Client\Curl;

class OrderClient
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly Logger $logger
    ) {}

    public function exportOrder(array $payload): string
    {
        $url = rtrim($this->config->getBaseUrl(), '/')
            . '/api/'
            . $this->config->getApiVersion()
            . '/orders';

        $this->curl->setTimeout($this->config->getTimeout());

        $this->curl->addHeader(
            'Content-Type',
            'application/json'
        );

        try {
            //$this->logger->info('Sending Order to ERP');
            $this->curl->post(
                $url,
                json_encode($payload)
            );
            //$this->logger->info('POST Completed');
        } catch (\Throwable $e) {

            $this->logger->error(
                'Curl Error: ' . $e->getMessage()
            );

            throw $e;
        }

        $response = $this->curl->getBody();

        //$this->logger->info('ERP Response');
        $this->logger->info($response);

        return $response;
    }
}
