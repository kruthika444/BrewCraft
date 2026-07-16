<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Api\JobRepositoryInterface;
use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Api\Client;
use BrewCraft\ErpIntegration\Model\JobFactory;

class PriceService
{
    public function __construct(
        private readonly Client $client,
        private readonly Logger $logger,
        private readonly JobRepositoryInterface $jobRepository,
        private readonly JobFactory $jobFactory
    ) {
    }

    public function getPrices(): array
    {
        $response = $this->client->getPrices();

        $prices = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(json_last_error_msg());
        }

        foreach ($prices as $price) {

            foreach (['sku', 'price'] as $field) {

                if (!array_key_exists($field, $price)) {
                    throw new \RuntimeException(
                        sprintf('Missing "%s" in price payload.', $field)
                    );
                }
            }
        }

        $job = $this->jobFactory->create();

        $job->setData([
            'job_type' => 'PRICE_SYNC',
            'status' => 'SUCCESS',
            'records_processed' => count($prices),
            'execution_time' => 0,
            'message' => 'Price payload fetched successfully.'
        ]);

        $this->jobRepository->save($job);

        $this->logger->info(
            sprintf('Fetched %d prices from ERP.', count($prices))
        );

        return $prices;
    }
}
