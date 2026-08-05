<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model\Service;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\QuoteRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class QuoteResponseService
{
    public function __construct(
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Accept an Admin proposal.
     *
     * @throws LocalizedException
     */
    public function accept(
        QuoteRequest $quoteRequest,
        int $customerId
    ): QuoteRequest {
        $this->validateResponse(
            $quoteRequest,
            $customerId
        );

        return $this->updateStatus(
            $quoteRequest,
            QuoteRequest::STATUS_ACCEPTED
        );
    }

    /**
     * Reject an Admin proposal.
     *
     * @throws LocalizedException
     */
    public function reject(
        QuoteRequest $quoteRequest,
        int $customerId
    ): QuoteRequest {
        $this->validateResponse(
            $quoteRequest,
            $customerId
        );

        return $this->updateStatus(
            $quoteRequest,
            QuoteRequest::STATUS_REJECTED
        );
    }

    public function canRespond(
        QuoteRequest $quoteRequest,
        int $customerId
    ): bool {
        try {
            $this->validateResponse(
                $quoteRequest,
                $customerId
            );

            return true;
        } catch (LocalizedException) {
            return false;
        }
    }

    /**
     * @throws LocalizedException
     */
    private function validateResponse(
        QuoteRequest $quoteRequest,
        int $customerId
    ): void {
        if (!$quoteRequest->getId()) {
            throw new LocalizedException(
                __('The quote request does not exist.')
            );
        }

        if ($customerId <= 0) {
            throw new LocalizedException(
                __('You must be logged in to respond to this quote.')
            );
        }

        if ($quoteRequest->getCustomerId() !== $customerId) {
            throw new LocalizedException(
                __('You are not allowed to respond to this quote request.')
            );
        }

        if (
            $quoteRequest->getStatus()
            !== QuoteRequest::STATUS_QUOTED
        ) {
            throw new LocalizedException(
                __(
                    'Only a quoted proposal can be accepted or rejected. Current status: %1.',
                    $quoteRequest->getStatus()
                )
            );
        }

        if ($quoteRequest->getData('proposed_subtotal') === null) {
            throw new LocalizedException(
                __('This quote request does not contain a price proposal.')
            );
        }

        $this->validateExpiry($quoteRequest);
    }

    /**
     * @throws LocalizedException
     */
    private function validateExpiry(
        QuoteRequest $quoteRequest
    ): void {
        $expiresAt = trim(
            (string)$quoteRequest->getData('expires_at')
        );

        if ($expiresAt === '') {
            return;
        }

        $expiryTimestamp = strtotime($expiresAt);

        if ($expiryTimestamp === false) {
            throw new LocalizedException(
                __('The quote expiry date is invalid.')
            );
        }

        $currentTimestamp = $this->dateTime->gmtTimestamp();

        if ($expiryTimestamp < $currentTimestamp) {
            throw new LocalizedException(
                __(
                    'This quote proposal expired on %1 and can no longer be accepted or rejected.',
                    $expiresAt
                )
            );
        }
    }

    /**
     * @throws LocalizedException
     */
    private function updateStatus(
        QuoteRequest $quoteRequest,
        string $newStatus
    ): QuoteRequest {
        $previousStatus = $quoteRequest->getStatus();

        $quoteRequest->setData(
            'status',
            $newStatus
        );

        try {
            $savedQuote = $this->quoteRequestRepository->save(
                $quoteRequest
            );

            $this->logger->info(
                'BrewCraft customer responded to quote proposal.',
                [
                    'quote_request_id' => (int)$savedQuote->getId(),
                    'quote_number' => $savedQuote->getQuoteNumber(),
                    'customer_id' => $savedQuote->getCustomerId(),
                    'previous_status' => $previousStatus,
                    'new_status' => $newStatus
                ]
            );

            return $savedQuote;
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Unable to save BrewCraft customer quote response.',
                [
                    'quote_request_id' => (int)$quoteRequest->getId(),
                    'quote_number' => $quoteRequest->getQuoteNumber(),
                    'customer_id' => $quoteRequest->getCustomerId(),
                    'requested_status' => $newStatus,
                    'exception' => $exception->getMessage()
                ]
            );

            throw new LocalizedException(
                __('Your response to the quote could not be saved.')
            );
        }
    }
}
