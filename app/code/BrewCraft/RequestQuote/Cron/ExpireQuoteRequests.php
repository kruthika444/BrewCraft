<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Cron;

use BrewCraft\RequestQuote\Model\Service\QuoteExpiryService;
use Psr\Log\LoggerInterface;

class ExpireQuoteRequests
{
    public function __construct(
        private readonly QuoteExpiryService $quoteExpiryService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $expiredCount = $this
                ->quoteExpiryService
                ->expireQuotes();

            $this->logger->debug(
                'BrewCraft RFQ expiry cron executed.',
                [
                    'expired_count' => $expiredCount
                ]
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Unable to execute BrewCraft RFQ expiry cron.',
                [
                    'exception' =>
                        $exception->getMessage()
                ]
            );
        }
    }
}
