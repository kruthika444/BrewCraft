<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'brewcraft_erp/general/enabled';
    private const XML_PATH_BASE_URL = 'brewcraft_erp/general/base_url';
    private const XML_PATH_API_VERSION = 'brewcraft_erp/general/api_version';
    private const XML_PATH_TIMEOUT = 'brewcraft_erp/general/timeout';

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_WEBSITE,
            $storeId
        );
    }

    public function getBaseUrl(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_BASE_URL,
            ScopeInterface::SCOPE_WEBSITE,
            $storeId
        );
    }

    public function getApiVersion(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_API_VERSION,
            ScopeInterface::SCOPE_WEBSITE,
            $storeId
        );
    }

    public function getTimeout(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_TIMEOUT,
            ScopeInterface::SCOPE_WEBSITE,
            $storeId
        );
    }
}
