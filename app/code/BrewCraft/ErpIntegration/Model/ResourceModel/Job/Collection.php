<?php
namespace BrewCraft\ErpIntegration\Model\ResourceModel\Job;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use BrewCraft\ErpIntegration\Model\Job as Model;
use BrewCraft\ErpIntegration\Model\ResourceModel\Job as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            Model::class,
            ResourceModel::class
        );
    }
}
