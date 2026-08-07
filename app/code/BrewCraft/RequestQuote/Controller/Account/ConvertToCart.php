<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Controller\Account;

use BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface;
use BrewCraft\RequestQuote\Model\Service\AcceptedQuoteCartService;
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

class ConvertToCart implements HttpPostActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly AcceptedQuoteCartService $acceptedQuoteCartService,
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
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(
                __('Your session has expired. Please try again.')
            );

            return $this->redirectFactory->create()->setPath('requestquote/account/index');
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        $entityId = (int)$this->request->getParam('id');

        if ($entityId <= 0) {
            $this->messageManager->addErrorMessage(
                __('The quote request ID is missing or invalid.')
            );

            return $this->redirectFactory->create()->setPath('requestquote/account/index');
        }

        try {
            $quoteRequest = $this->quoteRequestRepository->getById($entityId);

            $this->acceptedQuoteCartService->convertToCart(
                $quoteRequest,
                $customerId
            );

            $this->messageManager->addSuccessMessage(
                __(
                    'Quote %1 has been loaded into your shopping cart with the negotiated prices.',
                    $quoteRequest->getQuoteNumber()
                )
            );

            return $this->redirectFactory->create()->setPath('checkout/cart');
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(
                __('The requested quote could not be found.')
            );

            return $this->redirectFactory->create()->setPath('requestquote/account/index');
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());

            return $this->redirectAfterFailure($entityId, $customerId);
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Unexpected BrewCraft accepted quote cart conversion error.',
                [
                    'quote_request_id' => $entityId,
                    'customer_id' => $customerId,
                    'exception' => $exception->getMessage()
                ]
            );

            $this->messageManager->addErrorMessage(
                __('The accepted quote could not be loaded into your shopping cart.')
            );

            return $this->redirectFactory->create()->setPath('requestquote/account/index');
        }
    }

    private function redirectAfterFailure(
        int $entityId,
        int $customerId
    ): Redirect {
        try {
            $quoteRequest = $this->quoteRequestRepository->getById($entityId);

            if ($quoteRequest->getCustomerId() === $customerId) {
                return $this->redirectFactory->create()->setPath(
                    'requestquote/account/view',
                    ['quote_number' => $quoteRequest->getQuoteNumber()]
                );
            }
        } catch (\Throwable) {
        }

        return $this->redirectFactory->create()->setPath('requestquote/account/index');
    }
}
