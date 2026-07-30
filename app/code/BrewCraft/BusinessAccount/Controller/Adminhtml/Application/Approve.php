<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Controller\Adminhtml\Application;

use BrewCraft\BusinessAccount\Model\Service\BusinessAccountApprovalService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;

class Approve extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE =
        'BrewCraft_BusinessAccount::application_approve';

    public function __construct(
        Context $context,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly BusinessAccountApprovalService $approvalService
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->createRedirect();

        if (!$this->formKeyValidator->validate(
            $this->getRequest()
        )) {
            $this->messageManager->addErrorMessage(
                __('The form key is invalid or has expired.')
            );

            return $resultRedirect;
        }

        $entityId = (int)$this->getRequest()->getParam(
            'entity_id'
        );

        if ($entityId <= 0) {
            $this->messageManager->addErrorMessage(
                __('The business application ID is missing.')
            );

            return $this->resultRedirectFactory
                ->create()
                ->setPath(
                    'businessaccount/application/index'
                );
        }

        $adminComment = (string)$this->getRequest()->getParam(
            'admin_comment',
            ''
        );

        try {
            $businessAccount = $this->approvalService->approve(
                $entityId,
                $adminComment
            );

            $this->messageManager->addSuccessMessage(
                __(
                    'The business application for "%1" has been approved.',
                    $businessAccount->getCompanyName()
                )
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage(
                $exception->getMessage()
            );
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(
                __('The business application could not be approved.')
            );
        }

        return $resultRedirect;
    }

    private function createRedirect(): Redirect
    {
        $entityId = (int)$this->getRequest()->getParam(
            'entity_id'
        );

        return $this->resultRedirectFactory
            ->create()
            ->setPath(
                'businessaccount/application/view',
                ['entity_id' => $entityId]
            );
    }
}