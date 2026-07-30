<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Controller\Adminhtml\Application;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE =
        'BrewCraft_BusinessAccount::applications';

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
            'BrewCraft_BusinessAccount::applications'
        );

        $resultPage->addBreadcrumb(
            __('BrewCraft'),
            __('BrewCraft')
        );

        $resultPage->addBreadcrumb(
            __('Business Applications'),
            __('Business Applications')
        );

        $resultPage->getConfig()->getTitle()->prepend(
            __('Business Applications')
        );

        return $resultPage;
    }
}

