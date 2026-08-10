<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Model\Service;

use BrewCraft\RazorpayPayment\Model\Config;

class PaymentSignatureVerifier
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function verify(
        string $razorpayOrderId,
        string $razorpayPaymentId,
        string $receivedSignature
    ): bool {
        $secret = $this->config->getKeySecret();

        if (
            $razorpayOrderId === ''
            || $razorpayPaymentId === ''
            || $receivedSignature === ''
            || $secret === ''
        ) {
            return false;
        }

        $payload = sprintf(
            '%s|%s',
            $razorpayOrderId,
            $razorpayPaymentId
        );

        $generatedSignature = hash_hmac(
            'sha256',
            $payload,
            $secret
        );

        return hash_equals(
            $generatedSignature,
            $receivedSignature
        );
    }
}
