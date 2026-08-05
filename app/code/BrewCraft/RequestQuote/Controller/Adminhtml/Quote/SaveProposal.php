<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Controller\Adminhtml\Quote;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\Service\QuoteProposalService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class SaveProposal extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE =
        'BrewCraft_RequestQuote::quote_requests';

    public function __construct(
        Context $context,
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly QuoteProposalService $quoteProposalService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $entityId = (int)$this->getRequest()->getParam(
            'id'
        );

        if ($entityId <= 0) {
            $this->messageManager->addErrorMessage(
                __('The quote request ID is missing or invalid.')
            );

            return $this->redirectToGrid();
        }

        $proposedPrices = $this->getRequest()->getParam(
            'proposed_price',
            []
        );

        if (!is_array($proposedPrices)) {
            $proposedPrices = [];
        }

        $adminComment = trim(
            (string)$this->getRequest()->getParam(
                'admin_comment'
            )
        );

        $expiresAt = trim(
            (string)$this->getRequest()->getParam(
                'expires_at'
            )
        );

        try {
            $quoteRequest = $this
                ->quoteRequestRepository
                ->getById($entityId);

            $savedQuote = $this
                ->quoteProposalService
                ->saveProposal(
                    $quoteRequest,
                    $proposedPrices,
                    $adminComment,
                    $expiresAt
                );

            $this->messageManager->addSuccessMessage(
                __(
                    'The price proposal for quote request %1 has been saved and sent for customer review.',
                    $savedQuote->getQuoteNumber()
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
                'Unexpected Admin quote-proposal error.',
                [
                    'quote_request_id' => $entityId,
                    'exception' => $exception->getMessage()
                ]
            );

            $this->messageManager->addErrorMessage(
                __('The quote proposal could not be saved.')
            );
        }

        return $this->redirectToView(
            $entityId
        );
    }

    private function redirectToView(
        int $entityId
    ): Redirect {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this
            ->resultRedirectFactory
            ->create();

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
        $resultRedirect = $this
            ->resultRedirectFactory
            ->create();

        return $resultRedirect->setPath(
            'requestquote/quote/index'
        );
    }
}
