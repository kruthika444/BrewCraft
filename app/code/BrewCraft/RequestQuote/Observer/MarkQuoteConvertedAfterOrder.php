<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Observer;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\QuoteRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class MarkQuoteConvertedAfterOrder implements ObserverInterface
{
    public function __construct(
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();

        if (!$quote instanceof Quote || !$order instanceof Order) {
            return;
        }

        $quoteRequestId = (int)$quote->getData(
            'brewcraft_quote_request_id'
        );

        if ($quoteRequestId <= 0) {
            return;
        }

        try {
            $quoteRequest = $this->quoteRequestRepository->getById(
                $quoteRequestId
            );

            if (
                $quoteRequest->getStatus()
                === QuoteRequest::STATUS_CONVERTED
            ) {
                return;
            }

            if (
                (int)$quoteRequest->getCustomerId() > 0
                && (int)$quote->getCustomerId() > 0
                && (int)$quoteRequest->getCustomerId()
                    !== (int)$quote->getCustomerId()
            ) {
                $this->logger->warning(
                    'BrewCraft RFQ conversion customer mismatch.',
                    [
                        'quote_request_id' => $quoteRequestId,
                        'rfq_customer_id' =>
                            (int)$quoteRequest->getCustomerId(),
                        'checkout_customer_id' =>
                            (int)$quote->getCustomerId(),
                        'order_id' => (int)$order->getEntityId()
                    ]
                );

                return;
            }

            $order->setData(
                'brewcraft_quote_request_id',
                $quoteRequestId
            );
            $this->orderRepository->save($order);

            $quoteRequest->setStatus(
                QuoteRequest::STATUS_CONVERTED
            );
            $quoteRequest->setData(
                'checkout_quote_id',
                (int)$quote->getId()
            );
            $quoteRequest->setData(
                'order_id',
                (int)$order->getEntityId()
            );
            $quoteRequest->setData(
                'order_increment_id',
                (string)$order->getIncrementId()
            );
            $quoteRequest->setData(
                'converted_at',
                $this->dateTime->gmtDate()
            );

            $this->quoteRequestRepository->save(
                $quoteRequest
            );

            $this->logger->info(
                'BrewCraft quotation converted to order.',
                [
                    'quote_request_id' => $quoteRequestId,
                    'quote_number' => $quoteRequest->getQuoteNumber(),
                    'checkout_quote_id' => (int)$quote->getId(),
                    'order_id' => (int)$order->getEntityId(),
                    'order_increment_id' =>
                        (string)$order->getIncrementId()
                ]
            );
        } catch (\Throwable $exception) {
            $this->logger->critical(
                'Unable to mark BrewCraft quotation as converted.',
                [
                    'quote_request_id' => $quoteRequestId,
                    'checkout_quote_id' => (int)$quote->getId(),
                    'order_id' => (int)$order->getEntityId(),
                    'order_increment_id' =>
                        (string)$order->getIncrementId(),
                    'exception' => $exception->getMessage()
                ]
            );
        }
    }
}
