<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Controller\Adminhtml\Quote;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\QuoteRequest;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class View extends Action
{
    public const ADMIN_RESOURCE =
        'BrewCraft_RequestQuote::quote_requests';

    public const REGISTRY_KEY =
        'current_brewcraft_admin_quote_request';

    public function __construct(
        Context $context,
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly Registry $registry,
        private readonly PageFactory $pageFactory
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

            return $this->createRedirectToGrid();
        }

        try {
            $quoteRequest = $this
                ->quoteRequestRepository
                ->getById($entityId);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(
                __('The requested quote could not be found.')
            );

            return $this->createRedirectToGrid();
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(
                __('The quote request could not be loaded.')
            );

            return $this->createRedirectToGrid();
        }

        $this->registry->register(
            self::REGISTRY_KEY,
            $quoteRequest
        );

        /** @var Page $resultPage */
        $resultPage = $this->pageFactory->create();

        $resultPage->setActiveMenu(
            'BrewCraft_RequestQuote::quote_requests'
        );

        $resultPage->addBreadcrumb(
            __('BrewCraft'),
            __('BrewCraft')
        );

        $resultPage->addBreadcrumb(
            __('Quote Requests'),
            __('Quote Requests')
        );

        $resultPage->addBreadcrumb(
            $quoteRequest->getQuoteNumber(),
            $quoteRequest->getQuoteNumber()
        );

        $resultPage->getConfig()->getTitle()->prepend(
            __(
                'Quote Request %1',
                $quoteRequest->getQuoteNumber()
            )
        );

        return $resultPage;
    }

    private function createRedirectToGrid(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        return $resultRedirect->setPath(
            'requestquote/quote/index'
        );
    }
}
