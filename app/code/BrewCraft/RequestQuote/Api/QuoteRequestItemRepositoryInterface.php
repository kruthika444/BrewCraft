<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Api;

use BrewCraft\RequestQuote\Model\QuoteRequestItem;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequestItem\Collection;

interface QuoteRequestItemRepositoryInterface
{
    public function save(QuoteRequestItem $item): QuoteRequestItem;

    public function getById(int $entityId): QuoteRequestItem;

    public function getByQuoteRequestId(int $quoteRequestId): Collection;

    public function delete(QuoteRequestItem $item): bool;

    public function deleteById(int $entityId): bool;
}
