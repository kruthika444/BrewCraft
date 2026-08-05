<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Controller\Request;

use BrewCraft\RequestQuote\Model\Service\QuoteSubmissionService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

class Save implements HttpPostActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly QuoteSubmissionService $quoteSubmissionService,
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
            return $this->createRedirect(
                'customer/account/login'
            );
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(
                __('Your session has expired. Please try again.')
            );

            return $this->createRedirect(
                'checkout/cart'
            );
        }

        $customerId = (int)$this->customerSession->getCustomerId();

        $quoteName = trim(
            (string)$this->request->getParam('quote_name')
        );

        $customerMessage = trim(
            (string)$this->request->getParam('customer_message')
        );

        try {
            $quoteRequest = $this->quoteSubmissionService->submit(
                $customerId,
                $quoteName,
                $customerMessage
            );

            $this->messageManager->addSuccessMessage(
                __(
                    'Your quote request %1 has been submitted.',
                    $quoteRequest->getQuoteNumber()
                )
            );

            return $this->createRedirect(
                'requestquote/request/success',
                [
                    'quote_number' => $quoteRequest->getQuoteNumber()
                ]
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage(
                $exception->getMessage()
            );

            return $this->createRedirect(
                'requestquote/request/create'
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Unexpected BrewCraft quote request controller error.',
                [
                    'customer_id' => $customerId,
                    'exception' => $exception->getMessage()
                ]
            );

            $this->messageManager->addErrorMessage(
                __(
                    'The quote request could not be submitted. Please try again.'
                )
            );

            return $this->createRedirect(
                'requestquote/request/create'
            );
        }
    }

    private function createRedirect(
        string $path,
        array $arguments = []
    ): Redirect {
        $resultRedirect = $this->redirectFactory->create();

        return $resultRedirect->setPath(
            $path,
            $arguments
        );
    }
}
