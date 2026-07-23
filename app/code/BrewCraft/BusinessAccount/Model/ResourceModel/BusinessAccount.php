<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class BusinessAccount extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init(
            'brewcraft_business_account',
            'entity_id'
        );
    }
}
