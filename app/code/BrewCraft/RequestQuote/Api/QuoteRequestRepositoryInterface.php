<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Api;

use BrewCraft\RequestQuote\Model\QuoteRequest;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequest\Collection;

interface QuoteRequestRepositoryInterface
{
    public function save(QuoteRequest $quoteRequest): QuoteRequest;

    public function getById(int $entityId): QuoteRequest;

    public function getByQuoteNumber(string $quoteNumber): QuoteRequest;

    public function getByCustomerId(int $customerId): Collection;

    public function delete(QuoteRequest $quoteRequest): bool;

    public function deleteById(int $entityId): bool;
}
