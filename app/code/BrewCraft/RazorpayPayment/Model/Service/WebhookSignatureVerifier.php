<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Model\Service;

use BrewCraft\RazorpayPayment\Model\Config;

class WebhookSignatureVerifier
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function verify(
        string $rawBody,
        string $receivedSignature
    ): bool {
        $secret = $this->config->getWebhookSecret();

        if (
            $rawBody === ''
            || $receivedSignature === ''
            || $secret === ''
        ) {
            return false;
        }

        $generatedSignature = hash_hmac(
            'sha256',
            $rawBody,
            $secret
        );

        return hash_equals(
            $generatedSignature,
            $receivedSignature
        );
    }
}
