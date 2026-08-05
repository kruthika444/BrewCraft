<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Controller\Adminhtml\Quote;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE =
        'BrewCraft_RequestQuote::quote_requests';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
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

        $resultPage->getConfig()->getTitle()->prepend(
            __('Quote Requests')
        );

        return $resultPage;
    }
}
