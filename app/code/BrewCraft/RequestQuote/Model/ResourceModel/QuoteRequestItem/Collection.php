<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequestItem;

use BrewCraft\RequestQuote\Model\QuoteRequestItem as QuoteRequestItemModel;
use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequestItem as QuoteRequestItemResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(
            QuoteRequestItemModel::class,
            QuoteRequestItemResource::class
        );
    }
}
