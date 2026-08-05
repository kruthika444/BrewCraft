<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model;

use BrewCraft\RequestQuote\Api\QuoteRequestItemRepositoryInterface;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequestItem as ItemResource;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequestItem\Collection;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequestItem\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class QuoteRequestItemRepository implements QuoteRequestItemRepositoryInterface
{
    public function __construct(
        private readonly ItemResource $resource,
        private readonly QuoteRequestItemFactory $itemFactory,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function save(QuoteRequestItem $item): QuoteRequestItem
    {
        try {
            $this->resource->save($item);
        } catch (\Throwable $exception) {
            throw new CouldNotSaveException(
                __(
                    'The quote-request item could not be saved: %1',
                    $exception->getMessage()
                ),
                $exception
            );
        }

        return $item;
    }

    public function getById(int $entityId): QuoteRequestItem
    {
        if ($entityId <= 0) {
            throw new NoSuchEntityException(
                __('The quote-request item ID is invalid.')
            );
        }

        $item = $this->itemFactory->create();
        $this->resource->load($item, $entityId);

        if (!$item->getId()) {
            throw new NoSuchEntityException(
                __(
                    'The quote-request item with ID "%1" does not exist.',
                    $entityId
                )
            );
        }

        return $item;
    }

    public function getByQuoteRequestId(int $quoteRequestId): Collection
    {
        $collection = $this->collectionFactory->create();

        $collection->addFieldToFilter(
            'quote_request_id',
            $quoteRequestId
        );

        $collection->setOrder(
            'entity_id',
            'ASC'
        );

        return $collection;
    }

    public function delete(QuoteRequestItem $item): bool
    {
        try {
            $this->resource->delete($item);
        } catch (\Throwable $exception) {
            throw new CouldNotDeleteException(
                __(
                    'The quote-request item could not be deleted: %1',
                    $exception->getMessage()
                ),
                $exception
            );
        }

        return true;
    }

    public function deleteById(int $entityId): bool
    {
        return $this->delete(
            $this->getById($entityId)
        );
    }
}
