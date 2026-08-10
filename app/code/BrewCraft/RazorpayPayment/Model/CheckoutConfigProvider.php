<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;

class CheckoutConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getConfig(): array
    {
        if (!$this->config->isActive()) {
            return [];
        }

        return [
            'payment' => [
                'brewcraft_razorpay' => [
                    'key_id' => $this->config->getKeyId(),
                    'title' => $this->config->getTitle(),
                    'test_mode' => $this->config->isTestMode(),
                    'store_name' => $this->getStoreName(),
                ],
            ],
        ];
    }

    private function getStoreName(): string
    {
        return (string) $this->storeManager
            ->getStore()
            ->getName();
    }
}
