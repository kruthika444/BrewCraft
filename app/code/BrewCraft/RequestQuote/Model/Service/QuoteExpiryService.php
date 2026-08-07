<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class QuoteExpiryService
{
    private const STATUS_QUOTED = 'quoted';
    private const STATUS_EXPIRED = 'expired';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Expire quoted RFQs whose proposal expiry date has passed.
     *
     * @return int Number of RFQs expired.
     */
    public function expireQuotes(): int
    {
        $connection = $this->resourceConnection
            ->getConnection();

        $tableName = $this->resourceConnection
            ->getTableName(
                'brewcraft_quote_request'
            );

        $currentTime = $this->dateTime->gmtDate();

        try {
            $affectedRows = $connection->update(
                $tableName,
                [
                    'status' => self::STATUS_EXPIRED
                ],
                [
                    'status = ?' =>
                        self::STATUS_QUOTED,

                    'expires_at IS NOT NULL',

                    'expires_at < ?' =>
                        $currentTime
                ]
            );

            if ($affectedRows > 0) {
                $this->logger->info(
                    'BrewCraft RFQ expiry cron completed.',
                    [
                        'expired_count' => $affectedRows,
                        'executed_at' => $currentTime
                    ]
                );
            }

            return $affectedRows;
        } catch (\Throwable $exception) {
            $this->logger->error(
                'BrewCraft RFQ expiry cron failed.',
                [
                    'executed_at' => $currentTime,
                    'exception' =>
                        $exception->getMessage()
                ]
            );

            throw $exception;
        }
    }
}
