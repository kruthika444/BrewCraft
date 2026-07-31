<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Controller\Account;

use BrewCraft\BusinessAccount\Api\BusinessAccountRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

class Create implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly BusinessAccountRepositoryInterface $businessAccountRepository,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        if ($this->customerSession->isLoggedIn()) {
            $customerId = (int)$this->customerSession->getCustomerId();

            try {
                $businessAccount = $this
                    ->businessAccountRepository
                    ->getByCustomerId($customerId);

                if ($businessAccount->isPending()) {
                    $this->messageManager->addNoticeMessage(
                        __(
                            'You have already submitted a Business Account application. It is currently under review.'
                        )
                    );
                } elseif ($businessAccount->isApproved()) {
                    $this->messageManager->addSuccessMessage(
                        __(
                            'Your BrewCraft Business Account is already active.'
                        )
                    );
                } elseif ($businessAccount->isRejected()) {
                    $this->messageManager->addNoticeMessage(
                        __(
                            'Your previous application was not approved. Please review the feedback in your Business Account page.'
                        )
                    );
                } else {
                    $this->messageManager->addNoticeMessage(
                        __(
                            'A Business Account application already exists for your customer account.'
                        )
                    );
                }

                /** @var Redirect $resultRedirect */
                $resultRedirect = $this->redirectFactory->create();

                return $resultRedirect->setPath(
                    'businessaccount/account/index'
                );
            } catch (NoSuchEntityException) {
                /*
                 * The logged-in customer has no application.
                 * Continue to the registration page.
                 */
            } catch (\Throwable $exception) {
                $this->logger->error(
                    'Unable to check existing Business Account application.',
                    [
                        'customer_id' => $customerId,
                        'exception' => $exception->getMessage()
                    ]
                );

                $this->messageManager->addErrorMessage(
                    __(
                        'We could not verify your Business Account status. Please try again.'
                    )
                );

                /** @var Redirect $resultRedirect */
                $resultRedirect = $this->redirectFactory->create();

                return $resultRedirect->setPath(
                    'customer/account'
                );
            }
        }

        /** @var Page $resultPage */
        $resultPage = $this->pageFactory->create();

        $resultPage->getConfig()->getTitle()->set(
            __('Create Business Account')
        );

        return $resultPage;
    }
}
