<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Gateway\Http\Client;

use BrewCraft\RazorpayPayment\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class RazorpayClient
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create a Razorpay Order.
     *
     * @param int $amount Amount in smallest currency unit.
     * @param string $currency Currency code, for example INR.
     * @param string $receipt Internal Magento reference.
     * @param array<string, mixed> $notes
     *
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function createOrder(
        int $amount,
        string $currency,
        string $receipt,
        array $notes = []
    ): array {
        $keyId = $this->config->getKeyId();
        $keySecret = $this->config->getKeySecret();

        if ($keyId === '' || $keySecret === '') {
            throw new LocalizedException(
                __('Razorpay API credentials are not configured.')
            );
        }

        $payload = [
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'receipt' => $receipt,
            'notes' => $notes,
        ];

        try {
            $this->curl->setCredentials(
                $keyId,
                $keySecret
            );

            $this->curl->addHeader(
                'Content-Type',
                'application/json'
            );

            $this->curl->addHeader(
                'Accept',
                'application/json'
            );

            $this->curl->post(
                self::BASE_URL . '/orders',
                json_encode($payload, JSON_THROW_ON_ERROR)
            );

            $statusCode = $this->curl->getStatus();

            $responseBody = $this->curl->getBody();

            $response = json_decode(
                $responseBody,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if ($statusCode < 200 || $statusCode >= 300) {
                $errorMessage = $response['error']['description']
                    ?? 'Unknown Razorpay API error.';

                $this->logger->error(
                    'Razorpay order creation failed.',
                    [
                        'status_code' => $statusCode,
                        'error' => $errorMessage,
                    ]
                );

                throw new LocalizedException(
                    __(
                        'Unable to create Razorpay order: %1',
                        $errorMessage
                    )
                );
            }

            if (
                empty($response['id'])
                || !is_string($response['id'])
            ) {
                throw new LocalizedException(
                    __('Razorpay returned an invalid order response.')
                );
            }

            if ($this->config->isDebugEnabled()) {
                $this->logger->debug(
                    'Razorpay order created successfully.',
                    [
                        'razorpay_order_id' => $response['id'],
                        'amount' => $response['amount'] ?? $amount,
                        'currency' => $response['currency'] ?? $currency,
                        'receipt' => $receipt,
                    ]
                );
            }

            return $response;
        } catch (LocalizedException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Unexpected Razorpay API communication error.',
                [
                    'exception' => $exception->getMessage(),
                ]
            );

            throw new LocalizedException(
                __('Unable to communicate with Razorpay.')
            );
        }
    }

    /**
 * Fetch Razorpay payment details.
 *
 * @param string $paymentId
 * @return array<string, mixed>
 * @throws LocalizedException
 */
public function fetchPayment(
    string $paymentId
): array {
    $paymentId = trim($paymentId);

    if ($paymentId === '') {
        throw new LocalizedException(
            __('Razorpay payment ID is required.')
        );
    }

    $keyId = $this->config->getKeyId();
    $keySecret = $this->config->getKeySecret();

    if ($keyId === '' || $keySecret === '') {
        throw new LocalizedException(
            __('Razorpay API credentials are not configured.')
        );
    }

    try {
        $this->curl->setCredentials(
            $keyId,
            $keySecret
        );

        $this->curl->addHeader(
            'Accept',
            'application/json'
        );

        $this->curl->get(
            self::BASE_URL
            . '/payments/'
            . rawurlencode($paymentId)
        );

        $statusCode = $this->curl->getStatus();

        $response = json_decode(
            $this->curl->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorMessage =
                $response['error']['description']
                ?? 'Unable to fetch Razorpay payment.';

            throw new LocalizedException(
                __('Razorpay payment lookup failed: %1', $errorMessage)
            );
        }

        if (
            empty($response['id'])
            || !is_string($response['id'])
        ) {
            throw new LocalizedException(
                __('Razorpay returned invalid payment information.')
            );
        }

        return $response;
    } catch (LocalizedException $exception) {
        throw $exception;
    } catch (\Throwable $exception) {
        $this->logger->error(
            'Unable to fetch Razorpay payment.',
            [
                'payment_id' => $paymentId,
                'exception' => $exception->getMessage(),
            ]
        );

        throw new LocalizedException(
            __('Unable to communicate with Razorpay.')
        );
    }
}
}
