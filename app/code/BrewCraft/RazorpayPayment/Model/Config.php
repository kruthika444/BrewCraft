<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ACTIVE =
        'payment/brewcraft_razorpay/active';

    private const XML_PATH_TITLE =
        'payment/brewcraft_razorpay/title';

    private const XML_PATH_TEST_MODE =
        'payment/brewcraft_razorpay/test_mode';

    private const XML_PATH_KEY_ID =
        'payment/brewcraft_razorpay/key_id';

    private const XML_PATH_KEY_SECRET =
        'payment/brewcraft_razorpay/key_secret';

    private const XML_PATH_ORDER_STATUS =
        'payment/brewcraft_razorpay/order_status';

    private const XML_PATH_DEBUG =
        'payment/brewcraft_razorpay/debug';

    private const XML_PATH_WEBHOOK_SECRET =
    'payment/brewcraft_razorpay/webhook_secret';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isActive(
        ?int $storeId = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getTitle(
        ?int $storeId = null
    ): string {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isTestMode(
        ?int $storeId = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TEST_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getKeyId(
        ?int $storeId = null
    ): string {
        return trim(
            (string) $this->scopeConfig->getValue(
                self::XML_PATH_KEY_ID,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
        );
    }

    public function getKeySecret(
        ?int $storeId = null
    ): string {
        $encryptedSecret = (string) $this->scopeConfig->getValue(
            self::XML_PATH_KEY_SECRET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($encryptedSecret === '') {
            return '';
        }

        return trim(
            $this->encryptor->decrypt($encryptedSecret)
        );
    }

    public function getOrderStatus(
        ?int $storeId = null
    ): string {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_ORDER_STATUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isDebugEnabled(
        ?int $storeId = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getWebhookSecret(
    ?int $storeId = null
): string {
    $encryptedSecret = (string) $this->scopeConfig->getValue(
        self::XML_PATH_WEBHOOK_SECRET,
        ScopeInterface::SCOPE_STORE,
        $storeId
    );

    if ($encryptedSecret === '') {
        return '';
    }

    return trim(
        $this->encryptor->decrypt($encryptedSecret)
    );
}
}
