<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Handler extends Base
{
    protected $fileName = '/var/log/erp.log';

    protected $loggerType = Logger::INFO;
}
