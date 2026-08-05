<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Controller\Adminhtml\Quote;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\Service\QuoteStatusService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class MarkUnderReview extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE =
        'BrewCraft_RequestQuote::quote_requests';

    public function __construct(
        Context $context,
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly QuoteStatusService $quoteStatusService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $entityId = (int)$this->getRequest()->getParam('id');

        if ($entityId <= 0) {
            $this->messageManager->addErrorMessage(
                __('The quote request ID is missing or invalid.')
            );

            return $this->redirectToGrid();
        }

        try {
            $quoteRequest = $this
                ->quoteRequestRepository
                ->getById($entityId);

            $this->quoteStatusService->markUnderReview(
                $quoteRequest
            );

            $this->messageManager->addSuccessMessage(
                __(
                    'Quote request %1 is now under review.',
                    $quoteRequest->getQuoteNumber()
                )
            );
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(
                __('The requested quote could not be found.')
            );

            return $this->redirectToGrid();
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage(
                $exception->getMessage()
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Unexpected error while marking quote request under review.',
                [
                    'quote_request_id' => $entityId,
                    'exception' => $exception->getMessage()
                ]
            );

            $this->messageManager->addErrorMessage(
                __(
                    'The quote request status could not be updated.'
                )
            );
        }

        return $this->redirectToView($entityId);
    }

    private function redirectToView(
        int $entityId
    ): Redirect {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        return $resultRedirect->setPath(
            'requestquote/quote/view',
            [
                'id' => $entityId
            ]
        );
    }

    private function redirectToGrid(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        return $resultRedirect->setPath(
            'requestquote/quote/index'
        );
    }
}
