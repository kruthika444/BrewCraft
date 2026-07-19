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
    ) {
    }

    public function getProducts(): array
    {
        $response = $this->client->getProducts();

        $products = json_decode(
            $response,
            true
        );

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Invalid ERP JSON response: '
                . json_last_error_msg()
            );
        }

        if (!is_array($products)) {
            throw new \RuntimeException(
                'ERP product response must be an array.'
            );
        }

        foreach ($products as $product) {

            $this->validateProduct(
                $product
            );
        }

        $this->logger->info(
            sprintf(
                'Fetched %d products from ERP.',
                count($products)
            )
        );

        return $products;
    }

    private function validateProduct(
        array $product
    ): void {

        foreach (
            [
                'sku',
                'name',
                'price',
                'weight',
                'category_code',
                'status'
            ] as $field
        ) {

            if (!array_key_exists($field, $product)) {

                throw new \RuntimeException(
                    sprintf(
                        'Missing "%s" in ERP product payload.',
                        $field
                    )
                );
            }
        }
    }
}
