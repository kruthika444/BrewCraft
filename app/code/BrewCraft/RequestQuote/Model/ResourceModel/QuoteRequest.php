<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuoteRequest extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init(
            'brewcraft_quote_request',
            'entity_id'
        );
    }
}
