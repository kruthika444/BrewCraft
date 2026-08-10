<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Controller\Webhook;

use BrewCraft\RazorpayPayment\Model\Service\ProcessWebhookService;
use BrewCraft\RazorpayPayment\Model\Service\WebhookSignatureVerifier;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Payment implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly WebhookSignatureVerifier $signatureVerifier,
        private readonly ProcessWebhookService $processWebhookService,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        $rawBody =
            (string) $this->request
                ->getContent();

        $signature = trim(
            (string) $this->request
                ->getHeader(
                    'X-Razorpay-Signature'
                )
        );

        $eventId = trim(
            (string) $this->request
                ->getHeader(
                    'X-Razorpay-Event-Id'
                )
        );

        if (
            !$this->signatureVerifier->verify(
                $rawBody,
                $signature
            )
        ) {
            $this->logger->warning(
                'Invalid Razorpay webhook signature.'
            );

            return $result
                ->setHttpResponseCode(400)
                ->setData([
                    'success' => false,
                ]);
        }

        try {
            $payload = json_decode(
                $rawBody,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            /*
             * Razorpay provides a unique
             * X-Razorpay-Event-Id header.
             */
            if ($eventId === '') {
                /*
                 * Defensive fallback only.
                 */
                $eventId = hash(
                    'sha256',
                    $rawBody
                );
            }

            $this->processWebhookService
                ->execute(
                    $eventId,
                    $payload
                );

            return $result->setData([
                'success' => true,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->critical(
                'Razorpay webhook processing failed.',
                [
                    'event_id' => $eventId,
                    'exception' =>
                    $exception->getMessage(),
                ]
            );

            return $result
                ->setHttpResponseCode(500)
                ->setData([
                    'success' => false,
                ]);
        }
    }
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    public function validateForCsrf(
        RequestInterface $request
    ): ?bool {
        return true;
    }
}
