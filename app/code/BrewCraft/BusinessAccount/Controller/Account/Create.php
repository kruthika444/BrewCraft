<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Controller\Account;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Create implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        /** @var Page $resultPage */
        $resultPage = $this->pageFactory->create();

        $resultPage->getConfig()->getTitle()->set(
            __('Create Business Account')
        );

        return $resultPage;
    }
}
