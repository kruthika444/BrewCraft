<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model\Service;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\QuoteRequest;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class QuoteStatusService
{
    public function __construct(
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Move a pending quote request into under-review status.
     *
     * @throws LocalizedException
     */
    public function markUnderReview(
        QuoteRequest $quoteRequest
    ): QuoteRequest {
        $currentStatus = $quoteRequest->getStatus();

        if ($currentStatus === QuoteRequest::STATUS_UNDER_REVIEW) {
            throw new LocalizedException(
                __('This quote request is already under review.')
            );
        }

        if ($currentStatus !== QuoteRequest::STATUS_PENDING) {
            throw new LocalizedException(
                __(
                    'Only a pending quote request can be marked as under review. Current status: %1.',
                    $currentStatus
                )
            );
        }

        $quoteRequest->setData(
            'status',
            QuoteRequest::STATUS_UNDER_REVIEW
        );

        try {
            $savedQuote = $this->quoteRequestRepository->save(
                $quoteRequest
            );

            $this->logger->info(
                'BrewCraft quote request marked under review.',
                [
                    'quote_request_id' => (int)$savedQuote->getId(),
                    'quote_number' => $savedQuote->getQuoteNumber(),
                    'customer_id' => $savedQuote->getCustomerId(),
                    'previous_status' => $currentStatus,
                    'new_status' => QuoteRequest::STATUS_UNDER_REVIEW
                ]
            );

            return $savedQuote;
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Unable to mark BrewCraft quote request under review.',
                [
                    'quote_request_id' => (int)$quoteRequest->getId(),
                    'quote_number' => $quoteRequest->getQuoteNumber(),
                    'exception' => $exception->getMessage()
                ]
            );

            throw new LocalizedException(
                __(
                    'The quote request could not be marked as under review.'
                )
            );
        }
    }

    public function canMarkUnderReview(
        QuoteRequest $quoteRequest
    ): bool {
        return $quoteRequest->getStatus()
            === QuoteRequest::STATUS_PENDING;
    }
}
