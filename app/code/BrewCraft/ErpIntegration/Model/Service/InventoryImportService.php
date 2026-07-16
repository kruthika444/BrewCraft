<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Service;

use BrewCraft\ErpIntegration\Logger\Logger;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;

class InventoryImportService
{
    private const DEFAULT_SOURCE = 'default';

    public function __construct(
        private readonly GetSourceItemsBySkuInterface $getSourceItemsBySku,
        private readonly SourceItemsSaveInterface $sourceItemsSave,
        private readonly SourceItemInterfaceFactory $sourceItemFactory,
        private readonly Logger $logger
    ) {
    }

    public function import(array $inventory): array
    {
        $result = [
            'updated' => 0,
            'failed' => 0
        ];

        foreach ($inventory as $item) {

            try {

                $this->updateInventory($item);

                $result['updated']++;

            } catch (\Throwable $exception) {

                $result['failed']++;

                $this->logger->error(
                    sprintf(
                        'Inventory update failed for %s : %s',
                        $item['sku'],
                        $exception->getMessage()
                    )
                );
            }
        }

        return $result;
    }

    private function updateInventory(array $item): void
    {
        $sourceItems = $this->getSourceItemsBySku->execute(
            $item['sku']
        );

        if (empty($sourceItems)) {

            $sourceItem = $this->sourceItemFactory->create();

            $sourceItem->setSku($item['sku']);

            $sourceItem->setSourceCode(self::DEFAULT_SOURCE);

        } else {

            $sourceItem = reset($sourceItems);
        }

        $sourceItem->setQuantity((float)$item['qty']);

        $sourceItem->setStatus(
            (float)$item['qty'] > 0
                ? SourceItemInterface::STATUS_IN_STOCK
                : SourceItemInterface::STATUS_OUT_OF_STOCK
        );

        $this->sourceItemsSave->execute([
            $sourceItem
        ]);

        $this->logger->info(
            sprintf(
                'Inventory updated for %s',
                $item['sku']
            )
        );
    }
}
