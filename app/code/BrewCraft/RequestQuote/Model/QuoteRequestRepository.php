<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequest\Collection;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequest\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class QuoteRequestRepository implements QuoteRequestRepositoryInterface
{
    public function __construct(
        private readonly QuoteRequestResource $resource,
        private readonly QuoteRequestFactory $quoteRequestFactory,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function save(QuoteRequest $quoteRequest): QuoteRequest
    {
        try {
            $this->resource->save($quoteRequest);
        } catch (\Throwable $exception) {
            throw new CouldNotSaveException(
                __(
                    'The quote request could not be saved: %1',
                    $exception->getMessage()
                ),
                $exception
            );
        }

        return $quoteRequest;
    }

    public function getById(int $entityId): QuoteRequest
    {
        if ($entityId <= 0) {
            throw new NoSuchEntityException(
                __('The quote request ID is invalid.')
            );
        }

        $quoteRequest = $this->quoteRequestFactory->create();
        $this->resource->load($quoteRequest, $entityId);

        if (!$quoteRequest->getId()) {
            throw new NoSuchEntityException(
                __('The quote request with ID "%1" does not exist.', $entityId)
            );
        }

        return $quoteRequest;
    }

    public function getByQuoteNumber(string $quoteNumber): QuoteRequest
    {
        $quoteNumber = trim($quoteNumber);

        if ($quoteNumber === '') {
            throw new NoSuchEntityException(
                __('The quote request number is empty.')
            );
        }

        $quoteRequest = $this->quoteRequestFactory->create();

        $this->resource->load(
            $quoteRequest,
            $quoteNumber,
            'quote_number'
        );

        if (!$quoteRequest->getId()) {
            throw new NoSuchEntityException(
                __(
                    'The quote request "%1" does not exist.',
                    $quoteNumber
                )
            );
        }

        return $quoteRequest;
    }

    public function getByCustomerId(int $customerId): Collection
    {
        $collection = $this->collectionFactory->create();

        $collection->addFieldToFilter(
            'customer_id',
            $customerId
        );

        $collection->setOrder(
            'created_at',
            'DESC'
        );

        return $collection;
    }

    public function delete(QuoteRequest $quoteRequest): bool
    {
        try {
            $this->resource->delete($quoteRequest);
        } catch (\Throwable $exception) {
            throw new CouldNotDeleteException(
                __(
                    'The quote request could not be deleted: %1',
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
