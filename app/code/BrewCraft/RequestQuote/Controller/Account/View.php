<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Controller\Account;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\Service\BusinessCustomerEligibilityService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class View implements HttpGetActionInterface
{
    public const REGISTRY_KEY =
        'current_brewcraft_quote_request';

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly BusinessCustomerEligibilityService $eligibilityService,
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly RequestInterface $request,
        private readonly Registry $registry,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            $this->customerSession->setBeforeAuthUrl(
                $this->urlBuilder->getUrl(
                    'requestquote/account/index'
                )
            );

            return $this->createRedirect(
                'customer/account/login'
            );
        }

        $customerId = (int)$this->customerSession->getCustomerId();

        try {
            $this->eligibilityService->validate($customerId);
        } catch (LocalizedException $exception) {
            $this->messageManager->addNoticeMessage(
                $exception->getMessage()
            );

            return $this->createRedirect(
                'businessaccount/account/index'
            );
        } catch (\Throwable) {
            $this->messageManager->addErrorMessage(
                __(
                    'We could not verify your Business Account eligibility.'
                )
            );

            return $this->createRedirect(
                'customer/account'
            );
        }

        $quoteNumber = trim(
            (string)$this->request->getParam(
                'quote_number'
            )
        );

        if ($quoteNumber === '') {
            $this->messageManager->addErrorMessage(
                __('The quote request number is missing.')
            );

            return $this->createRedirect(
                'requestquote/account/index'
            );
        }

        try {
            $quoteRequest = $this
                ->quoteRequestRepository
                ->getByQuoteNumber($quoteNumber);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(
                __('The requested quote could not be found.')
            );

            return $this->createRedirect(
                'requestquote/account/index'
            );
        }

        /*
         * The quote must belong to the authenticated customer.
         */
        if ($quoteRequest->getCustomerId() !== $customerId) {
            $this->messageManager->addErrorMessage(
                __('You are not allowed to view this quote request.')
            );

            return $this->createRedirect(
                'requestquote/account/index'
            );
        }

        $this->registry->register(
            self::REGISTRY_KEY,
            $quoteRequest
        );

        /** @var Page $resultPage */
        $resultPage = $this->pageFactory->create();

        $resultPage->getConfig()->getTitle()->set(
            __(
                'Quote Request %1',
                $quoteRequest->getQuoteNumber()
            )
        );

        return $resultPage;
    }

    private function createRedirect(string $path): Redirect
    {
        $resultRedirect = $this->redirectFactory->create();

        return $resultRedirect->setPath($path);
    }
}
