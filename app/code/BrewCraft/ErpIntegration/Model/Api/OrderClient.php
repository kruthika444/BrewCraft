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
    ) {
    }

    public function exportOrder(array $payload): string
    {
        $url = rtrim(trim($this->config->getBaseUrl()), '/')
            . '/api/'
            . trim($this->config->getApiVersion(), '/')
            . '/orders';

        $jsonPayload = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
        );

        $this->curl->setTimeout(
            $this->config->getTimeout()
        );

        $this->curl->addHeader(
            'Content-Type',
            'application/json'
        );

        $this->curl->addHeader(
            'Accept',
            'application/json'
        );

        $this->logger->info(
            sprintf('Sending order to ERP: %s', $url)
        );

        $this->logger->debug($jsonPayload);

        $this->curl->post(
            $url,
            $jsonPayload
        );

        $statusCode = $this->curl->getStatus();
        $response = $this->curl->getBody();

        $this->logger->info(
            sprintf(
                'ERP order response status: %d',
                $statusCode
            )
        );

        $this->logger->debug(
            sprintf(
                'ERP order response body: %s',
                $response
            )
        );

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(
                sprintf(
                    'ERP order export failed with HTTP status %d. Response: %s',
                    $statusCode,
                    $response
                )
            );
        }

        return $response;
    }
}
