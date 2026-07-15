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
    ) {
    }

    public function getProducts(): string
    {
        $url = rtrim($this->config->getBaseUrl(), '/')
            . '/api/'
            . $this->config->getApiVersion()
            . '/products';

//         $url = rtrim($this->config->getBaseUrl(), '/')
//     . '/api/'
//     . $this->config->getApiVersion()
//     . '/products';

// var_dump($url);
// die();
        $this->curl->setTimeout($this->config->getTimeout());

        $this->logger->info('Calling ERP');
        $this->logger->info($url);

        $this->curl->get($url);

        $response = $this->curl->getBody();

        $this->logger->info('ERP Response');
        $this->logger->info($response);

        return $response;
    }
}
