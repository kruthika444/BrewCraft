<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Model\ResourceModel\BusinessAccount;

use BrewCraft\BusinessAccount\Model\BusinessAccount;
use BrewCraft\BusinessAccount\Model\ResourceModel\BusinessAccount as BusinessAccountResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected $_eventPrefix =
        'brewcraft_business_account_collection';

    protected $_eventObject =
        'business_account_collection';

    protected function _construct(): void
    {
        $this->_init(
            BusinessAccount::class,
            BusinessAccountResource::class
        );
    }
}
