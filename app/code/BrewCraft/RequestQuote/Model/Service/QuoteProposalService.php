<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model\Service;

use BrewCraft\RequestQuote\Api\QuoteRequestItemRepositoryInterface;
use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\QuoteRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class QuoteProposalService
{
    private const MAX_ADMIN_COMMENT_LENGTH = 5000;

    public function __construct(
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly QuoteRequestItemRepositoryInterface $itemRepository,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Save the Admin price proposal and move the request to Quoted.
     *
     * Expected item price format:
     *
     * [
     *     quote_request_item_id => proposed_unit_price
     * ]
     *
     * @throws LocalizedException
     */
    public function saveProposal(
        QuoteRequest $quoteRequest,
        array $proposedPrices,
        ?string $adminComment = null,
        ?string $expiresAt = null
    ): QuoteRequest {
        $this->validateQuoteStatus($quoteRequest);

        $adminComment = trim((string)$adminComment);
        $expiresAt = trim((string)$expiresAt);

        $this->validateAdminComment($adminComment);

        $quoteItems = $this->itemRepository->getByQuoteRequestId(
            (int)$quoteRequest->getId()
        );

        if ($quoteItems->getSize() === 0) {
            throw new LocalizedException(
                __('This quote request does not contain any items.')
            );
        }

        $normalizedPrices = $this->validateAndNormalizePrices(
            $quoteRequest,
            $quoteItems,
            $proposedPrices
        );

        $normalizedExpiryDate = $this->normalizeExpiryDate(
            $expiresAt
        );

        $connection = $this
            ->resourceConnection
            ->getConnection();

        $connection->beginTransaction();

        try {
            $proposedSubtotal = 0.0;

            foreach ($quoteItems as $quoteItem) {
                $itemId = (int)$quoteItem->getId();

                $proposedPrice = $normalizedPrices[$itemId];
                $quantity = (float)$quoteItem->getRequestedQty();

                $proposedRowTotal = round(
                    $proposedPrice * $quantity,
                    4
                );

                $quoteItem->setData(
                    'proposed_price',
                    $proposedPrice
                );

                $quoteItem->setData(
                    'proposed_row_total',
                    $proposedRowTotal
                );

                $this->itemRepository->save(
                    $quoteItem
                );

                $proposedSubtotal += $proposedRowTotal;
            }

            $proposedSubtotal = round(
                $proposedSubtotal,
                4
            );

            $quoteRequest->setData(
                'proposed_subtotal',
                $proposedSubtotal
            );

            $quoteRequest->setData(
                'admin_comment',
                $adminComment !== ''
                    ? $adminComment
                    : null
            );

            $quoteRequest->setData(
                'expires_at',
                $normalizedExpiryDate
            );

            $quoteRequest->setData(
                'status',
                QuoteRequest::STATUS_QUOTED
            );

            $savedQuote = $this->quoteRequestRepository->save(
                $quoteRequest
            );

            $connection->commit();

            $this->logger->info(
                'BrewCraft quote proposal saved.',
                [
                    'quote_request_id' => (int)$savedQuote->getId(),
                    'quote_number' => $savedQuote->getQuoteNumber(),
                    'customer_id' => $savedQuote->getCustomerId(),
                    'proposed_subtotal' => $proposedSubtotal,
                    'item_count' => (int)$quoteItems->getSize(),
                    'status' => QuoteRequest::STATUS_QUOTED
                ]
            );

            return $savedQuote;
        } catch (LocalizedException $exception) {
            $connection->rollBack();

            throw $exception;
        } catch (\Throwable $exception) {
            $connection->rollBack();

            $this->logger->error(
                'Unable to save BrewCraft quote proposal.',
                [
                    'quote_request_id' => (int)$quoteRequest->getId(),
                    'quote_number' => $quoteRequest->getQuoteNumber(),
                    'exception' => $exception->getMessage()
                ]
            );

            throw new LocalizedException(
                __('The quote proposal could not be saved.')
            );
        }
    }

    private function validateQuoteStatus(
        QuoteRequest $quoteRequest
    ): void {
        if (!$quoteRequest->getId()) {
            throw new LocalizedException(
                __('The quote request does not exist.')
            );
        }

        if (
            $quoteRequest->getStatus()
            !== QuoteRequest::STATUS_UNDER_REVIEW
        ) {
            throw new LocalizedException(
                __(
                    'A proposal can only be created when the quote request is under review. Current status: %1.',
                    $quoteRequest->getStatus()
                )
            );
        }
    }

    private function validateAdminComment(
        string $adminComment
    ): void {
        if (
            mb_strlen($adminComment)
            > self::MAX_ADMIN_COMMENT_LENGTH
        ) {
            throw new LocalizedException(
                __(
                    'The Admin comment cannot exceed %1 characters.',
                    self::MAX_ADMIN_COMMENT_LENGTH
                )
            );
        }
    }

    /**
     * @return array<int, float>
     */
    private function validateAndNormalizePrices(
        QuoteRequest $quoteRequest,
        iterable $quoteItems,
        array $proposedPrices
    ): array {
        $normalizedPrices = [];

        foreach ($quoteItems as $quoteItem) {
            $itemId = (int)$quoteItem->getId();

            /*
             * Extra ownership protection:
             * every loaded item must belong to the current quote.
             */
            if (
                $quoteItem->getQuoteRequestId()
                !== (int)$quoteRequest->getId()
            ) {
                throw new LocalizedException(
                    __('An invalid quote item was detected.')
                );
            }

            if (!array_key_exists($itemId, $proposedPrices)) {
                throw new LocalizedException(
                    __(
                        'Enter a proposed price for item ID %1.',
                        $itemId
                    )
                );
            }

            $rawPrice = trim(
                (string)$proposedPrices[$itemId]
            );

            if ($rawPrice === '' || !is_numeric($rawPrice)) {
                throw new LocalizedException(
                    __(
                        'Enter a valid numeric proposed price for item ID %1.',
                        $itemId
                    )
                );
            }

            $price = round(
                (float)$rawPrice,
                4
            );

            if ($price <= 0) {
                throw new LocalizedException(
                    __(
                        'The proposed price for item ID %1 must be greater than zero.',
                        $itemId
                    )
                );
            }

            $normalizedPrices[$itemId] = $price;
        }

        return $normalizedPrices;
    }

    private function normalizeExpiryDate(
        string $expiresAt
    ): ?string {
        if ($expiresAt === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $expiresAt
        );

        $errors = \DateTimeImmutable::getLastErrors();

        if (
            !$date
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
        ) {
            throw new LocalizedException(
                __('Enter a valid quote expiry date.')
            );
        }

        $today = new \DateTimeImmutable('today');

        if ($date < $today) {
            throw new LocalizedException(
                __('The quote expiry date cannot be in the past.')
            );
        }

        /*
         * Quote remains valid through the selected date.
         */
        return $date
            ->setTime(23, 59, 59)
            ->format('Y-m-d H:i:s');
    }
}
