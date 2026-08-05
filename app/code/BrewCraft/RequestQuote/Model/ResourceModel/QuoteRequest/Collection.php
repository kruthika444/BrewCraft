<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequest;

use BrewCraft\RequestQuote\Model\QuoteRequest as QuoteRequestModel;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(
            QuoteRequestModel::class,
            QuoteRequestResource::class
        );
    }
}
