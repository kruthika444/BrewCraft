<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Controller\Account;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\Service\BusinessCustomerEligibilityService;
use BrewCraft\RequestQuote\Model\Service\QuoteResponseService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

class Accept implements HttpPostActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly BusinessCustomerEligibilityService $eligibilityService,
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly QuoteResponseService $quoteResponseService,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->redirectToLogin();
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(
                __('Your session has expired. Please try again.')
            );

            return $this->redirectToQuoteList();
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        $entityId = (int)$this->request->getParam('id');

        if ($entityId <= 0) {
            $this->messageManager->addErrorMessage(
                __('The quote request ID is missing or invalid.')
            );

            return $this->redirectToQuoteList();
        }

        try {
            $this->eligibilityService->validate($customerId);

            $quoteRequest = $this
                ->quoteRequestRepository
                ->getById($entityId);

            $savedQuote = $this->quoteResponseService->accept(
                $quoteRequest,
                $customerId
            );

            $this->messageManager->addSuccessMessage(
                __(
                    'You accepted quote proposal %1.',
                    $savedQuote->getQuoteNumber()
                )
            );

            return $this->redirectToQuoteView(
                $savedQuote->getQuoteNumber()
            );
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(
                __('The requested quote could not be found.')
            );

            return $this->redirectToQuoteList();
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage(
                $exception->getMessage()
            );

            return $this->redirectAfterFailure($entityId);
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Unexpected customer quote acceptance error.',
                [
                    'quote_request_id' => $entityId,
                    'customer_id' => $customerId,
                    'exception' => $exception->getMessage()
                ]
            );

            $this->messageManager->addErrorMessage(
                __('The quote proposal could not be accepted.')
            );

            return $this->redirectToQuoteList();
        }
    }

    private function redirectAfterFailure(
        int $entityId
    ): Redirect {
        try {
            $quoteRequest = $this
                ->quoteRequestRepository
                ->getById($entityId);

            if (
                $quoteRequest->getCustomerId()
                === (int)$this->customerSession->getCustomerId()
            ) {
                return $this->redirectToQuoteView(
                    $quoteRequest->getQuoteNumber()
                );
            }
        } catch (\Throwable) {
            // Redirect to list below.
        }

        return $this->redirectToQuoteList();
    }

    private function redirectToQuoteView(
        string $quoteNumber
    ): Redirect {
        $resultRedirect = $this->redirectFactory->create();

        return $resultRedirect->setPath(
            'requestquote/account/view',
            [
                'quote_number' => $quoteNumber
            ]
        );
    }

    private function redirectToQuoteList(): Redirect
    {
        $resultRedirect = $this->redirectFactory->create();

        return $resultRedirect->setPath(
            'requestquote/account/index'
        );
    }

    private function redirectToLogin(): Redirect
    {
        $resultRedirect = $this->redirectFactory->create();

        return $resultRedirect->setPath(
            'customer/account/login'
        );
    }
}
