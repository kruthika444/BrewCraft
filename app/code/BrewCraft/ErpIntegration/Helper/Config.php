<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    /*
     * General configuration
     */
    private const XML_PATH_ENABLED =
        'brewcraft_erp/general/enabled';

    private const XML_PATH_BASE_URL =
        'brewcraft_erp/general/base_url';

    private const XML_PATH_API_VERSION =
        'brewcraft_erp/general/api_version';

    private const XML_PATH_TIMEOUT =
        'brewcraft_erp/general/timeout';

    /*
     * Order export configuration
     */
    private const XML_PATH_ORDER_EXPORT_ENABLED =
        'brewcraft_erp/order_export/enabled';

    private const XML_PATH_QUEUE_ENABLED =
        'brewcraft_erp/order_export/queue_enabled';

    private const XML_PATH_RETRY_ATTEMPTS =
        'brewcraft_erp/order_export/retry_attempts';

    private const XML_PATH_RETRY_DELAY =
        'brewcraft_erp/order_export/retry_delay';

    /*
     * Import configuration
     */
    private const XML_PATH_PRODUCT_SYNC_ENABLED =
        'brewcraft_erp/import/product_sync_enabled';

    private const XML_PATH_INVENTORY_SYNC_ENABLED =
        'brewcraft_erp/import/inventory_sync_enabled';

    private const XML_PATH_PRICE_SYNC_ENABLED =
        'brewcraft_erp/import/price_sync_enabled';

    /**
     * Check whether the entire ERP integration is enabled.
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the ERP base URL.
     */
    public function getBaseUrl(?int $storeId = null): string
    {
        return rtrim(
            (string)$this->scopeConfig->getValue(
                self::XML_PATH_BASE_URL,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
            '/'
        );
    }

    /**
     * Return the configured ERP API version.
     */
    public function getApiVersion(?int $storeId = null): string
    {
        return trim(
            (string)$this->scopeConfig->getValue(
                self::XML_PATH_API_VERSION,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
            '/'
        );
    }

    /**
     * Return the API connection timeout.
     */
    public function getTimeout(?int $storeId = null): int
    {
        $timeout = (int)$this->scopeConfig->getValue(
            self::XML_PATH_TIMEOUT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        /*
         * Prevent an invalid zero or negative timeout.
         */
        return $timeout > 0 ? $timeout : 30;
    }

    /**
     * Check whether order export is enabled.
     */
    public function isOrderExportEnabled(
        ?int $storeId = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ORDER_EXPORT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check whether queue-based order export is enabled.
     */
    public function isQueueEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_QUEUE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the maximum number of ERP export attempts.
     */
    public function getRetryAttempts(?int $storeId = null): int
    {
        $attempts = (int)$this->scopeConfig->getValue(
            self::XML_PATH_RETRY_ATTEMPTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        /*
         * There must always be at least one attempt.
         */
        return $attempts > 0 ? $attempts : 1;
    }

    /**
     * Return the delay between retry attempts in seconds.
     */
    public function getRetryDelay(?int $storeId = null): int
    {
        $delay = (int)$this->scopeConfig->getValue(
            self::XML_PATH_RETRY_DELAY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        /*
         * A delay of zero is valid and means retry immediately.
         */
        return max(0, $delay);
    }

    /**
     * Check whether category and product synchronization is enabled.
     */
    public function isProductSyncEnabled(
        ?int $storeId = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PRODUCT_SYNC_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check whether inventory synchronization is enabled.
     */
    public function isInventorySyncEnabled(
        ?int $storeId = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_INVENTORY_SYNC_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check whether price synchronization is enabled.
     */
    public function isPriceSyncEnabled(
        ?int $storeId = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PRICE_SYNC_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
