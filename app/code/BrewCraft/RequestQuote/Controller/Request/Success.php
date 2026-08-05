<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Controller\Request;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Success implements HttpGetActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly RequestInterface $request,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->createRedirect(
                'customer/account/login'
            );
        }

        $quoteNumber = trim(
            (string)$this->request->getParam('quote_number')
        );

        if ($quoteNumber === '') {
            return $this->createRedirect(
                'customer/account'
            );
        }

        try {
            $quoteRequest = $this
                ->quoteRequestRepository
                ->getByQuoteNumber($quoteNumber);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(
                __('The quote request could not be found.')
            );

            return $this->createRedirect(
                'customer/account'
            );
        }

        if (
            $quoteRequest->getCustomerId()
            !== (int)$this->customerSession->getCustomerId()
        ) {
            $this->messageManager->addErrorMessage(
                __('You cannot view this quote request.')
            );

            return $this->createRedirect(
                'customer/account'
            );
        }

        /** @var Page $resultPage */
        $resultPage = $this->pageFactory->create();

        $resultPage->getConfig()->getTitle()->set(
            __('Quote Request Submitted')
        );

        return $resultPage;
    }

    private function createRedirect(string $path): Redirect
    {
        $resultRedirect = $this->redirectFactory->create();

        return $resultRedirect->setPath($path);
    }
}
