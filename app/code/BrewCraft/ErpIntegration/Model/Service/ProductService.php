<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Api\Client;

class ProductService
{
    public function __construct(
        private readonly Client $client,
        private readonly Logger $logger
    ) {}

    /**
     * Fetch products from ERP.
     *
     * @return array
     */
    public function getProducts(): array
    {
        $response = $this->client->getProducts();

        $products = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Invalid ERP JSON Response: ' . json_last_error_msg()
            );
        }

        if (!is_array($products)) {
            throw new \RuntimeException(
                'ERP response is not a valid array.'
            );
        }

        $this->logger->info(
            sprintf('Fetched %d products from ERP.', count($products))
        );

        // foreach ($products as $product) {
        //     foreach (
        //         [
        //             'sku',
        //             'name',
        //             'price',
        //             'status'
        //         ] as $field
        //     ) {
        //         if (!array_key_exists($field, $product)) {
        //             throw new \RuntimeException(
        //                 sprintf(
        //                     'Missing "%s" for product.',
        //                     $field
        //                 )
        //             );
        //         }
        //     }
        // }


        return $products;
    }
}
