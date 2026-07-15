<?php

namespace BrewCraft\ErpIntegration\Model;

use Magento\Framework\Model\AbstractModel;

class Job extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(
            \BrewCraft\ErpIntegration\Model\ResourceModel\Job::class
        );
    }
}
