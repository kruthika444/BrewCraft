<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Controller\Adminhtml\Application;

use BrewCraft\BusinessAccount\Api\BusinessAccountRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class View extends Action
{
    public const ADMIN_RESOURCE =
        'BrewCraft_BusinessAccount::application_view';

    public const REGISTRY_KEY =
        'current_brewcraft_business_application';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly BusinessAccountRepositoryInterface $businessAccountRepository,
        private readonly Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $entityId = (int)$this->getRequest()->getParam(
            'entity_id'
        );

        if ($entityId <= 0) {
            $this->messageManager->addErrorMessage(
                __('The business application ID is missing.')
            );

            return $this->createRedirectToGrid();
        }

        try {
            $businessAccount = $this
                ->businessAccountRepository
                ->getById($entityId);
        } catch (NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage(
                __('The requested business application no longer exists.')
            );

            return $this->createRedirectToGrid();
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(
                __('The business application could not be loaded.')
            );

            return $this->createRedirectToGrid();
        }

        /*
         * Register the loaded model so the Admin block can access it.
         */
        $this->registry->register(
            self::REGISTRY_KEY,
            $businessAccount
        );

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

        $resultPage->addBreadcrumb(
            __('Application Details'),
            __('Application Details')
        );

        $resultPage->getConfig()->getTitle()->prepend(
            __('Business Application')
        );

        $resultPage->getConfig()->getTitle()->prepend(
            $businessAccount->getCompanyName()
        );

        return $resultPage;
    }

    private function createRedirectToGrid(): Redirect
    {
        return $this->resultRedirectFactory
            ->create()
            ->setPath(
                'businessaccount/application/index'
            );
    }
}