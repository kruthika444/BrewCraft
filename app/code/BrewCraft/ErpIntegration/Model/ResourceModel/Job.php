<?php

namespace BrewCraft\ErpIntegration\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Job extends AbstractDb
{
    protected function _construct()
    {
        $this->_init(
            'brewcraft_erp_job',
            'job_id'
        );
    }
}
