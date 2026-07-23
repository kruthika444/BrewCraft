<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Controller\Account;

use BrewCraft\BusinessAccount\Model\Service\BusinessAccountRegistrationService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\Generic as GenericSession;

class Save implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly BusinessAccountRegistrationService $registrationService,
        private readonly ManagerInterface $messageManager,
        private readonly GenericSession $session
    ) {
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->redirectFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(
                __('Your session has expired. Please submit the form again.')
            );

            return $resultRedirect->setPath(
                'businessaccount/account/create'
            );
        }

        $data = (array)$this->request->getPostValue();

        /*
         * Preserve submitted values when validation fails.
         * We can use this later to repopulate the registration form.
         */
        $this->session->setBusinessAccountFormData($data);

        try {
            $businessAccount = $this->registrationService->register(
                $data
            );

            $this->session->unsBusinessAccountFormData();

            $this->session->setBusinessAccountApplicationId(
                $businessAccount->getEntityId()
            );

            $this->session->setBusinessAccountCompanyName(
                $businessAccount->getCompanyName()
            );

            return $resultRedirect->setPath(
                'businessaccount/account/success'
            );
        } catch (\Magento\Framework\Exception\LocalizedException $exception) {
            $this->messageManager->addErrorMessage(
                $exception->getMessage()
            );
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(
                __(
                    'The business account application could not be submitted. Please try again.'
                )
            );
        }

        return $resultRedirect->setPath(
            'businessaccount/account/create'
        );
    }
}
