<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Logger\Logger;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class PriceImportService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Logger $logger
    ) {
    }

    public function import(array $prices): array
    {
        $updated = 0;
        $failed = 0;

        foreach ($prices as $item) {

            try {

                $product = $this->productRepository->get($item['sku']);

                $product->setPrice((float)$item['price']);

                $product->setSpecialPrice(
                    $item['special_price'] ?? null
                );

                $product->setSpecialFromDate(
                    $item['special_from'] ?? null
                );

                $product->setSpecialToDate(
                    $item['special_to'] ?? null
                );

                $this->productRepository->save($product);

                $updated++;

                $this->logger->info(
                    sprintf(
                        'Price updated for %s',
                        $item['sku']
                    )
                );

            } catch (NoSuchEntityException $exception) {

                $failed++;

                $this->logger->error(
                    sprintf(
                        'Product %s not found.',
                        $item['sku']
                    )
                );

            } catch (\Throwable $exception) {

                $failed++;

                $this->logger->error(
                    $exception->getMessage()
                );
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed
        ];
    }
}
