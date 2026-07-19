<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Api\Client;

class CategoryService
{
    public function __construct(
        private readonly Client $client,
        private readonly Logger $logger
    ) {
    }

    /**
     * Fetch all categories from ERP.
     */
    public function getCategories(): array
    {
        $response = $this->client->getCategories();

        $categories = json_decode(
            $response,
            true
        );

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Invalid ERP JSON response: '
                . json_last_error_msg()
            );
        }

        if (!is_array($categories)) {
            throw new \RuntimeException(
                'ERP category response must be an array.'
            );
        }

        foreach ($categories as $category) {

            $this->validateCategory($category);
        }

        $this->logger->info(
            sprintf(
                'Fetched %d categories from ERP.',
                count($categories)
            )
        );

        return $categories;
    }

    /**
     * Validate one ERP category.
     */
    private function validateCategory(array $category): void
    {
        foreach (
            [
                'code',
                'name',
                'parent_code',
                'status'
            ] as $field
        ) {

            if (!array_key_exists($field, $category)) {

                throw new \RuntimeException(
                    sprintf(
                        'Category payload missing "%s".',
                        $field
                    )
                );
            }
        }
    }
}
