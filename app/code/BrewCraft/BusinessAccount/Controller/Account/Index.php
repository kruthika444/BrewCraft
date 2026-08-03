<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Controller\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function execute(): ResultInterface
    {
        /*
         * The Business Account status page contains customer-specific
         * information, so only logged-in customers may access it.
         */
        if (!$this->customerSession->isLoggedIn()) {
            /*
             * Store a complete URL, not only the Magento route.
             */
            $businessAccountUrl = $this->urlBuilder->getUrl(
                'businessaccount/account/index'
            );

            $this->customerSession->setBeforeAuthUrl(
                $businessAccountUrl
            );

            /** @var Redirect $resultRedirect */
            $resultRedirect = $this->redirectFactory->create();

            return $resultRedirect->setUrl(
                $this->urlBuilder->getUrl(
                    'customer/account/login'
                )
            );
        }

        /** @var Page $resultPage */
        $resultPage = $this->pageFactory->create();

        $resultPage->getConfig()->getTitle()->set(
            __('Business Account')
        );

        return $resultPage;
    }
}
