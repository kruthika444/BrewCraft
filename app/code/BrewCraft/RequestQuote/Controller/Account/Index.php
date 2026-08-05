<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Controller\Account;

use BrewCraft\RequestQuote\Model\Service\BusinessCustomerEligibilityService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly BusinessCustomerEligibilityService $eligibilityService,
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

        /** @var Page $resultPage */
        $resultPage = $this->pageFactory->create();

        $resultPage->getConfig()->getTitle()->set(
            __('My Quote Requests')
        );

        return $resultPage;
    }

    private function createRedirect(string $path): Redirect
    {
        $resultRedirect = $this->redirectFactory->create();

        return $resultRedirect->setPath($path);
    }
}
