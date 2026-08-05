<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Controller\Request;

use BrewCraft\RequestQuote\Model\Service\BusinessCustomerEligibilityService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;

class Create implements HttpGetActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly BusinessCustomerEligibilityService $eligibilityService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->redirectToLogin();
        }

        $customerId = (int)$this->customerSession->getCustomerId();

        try {
            $this->eligibilityService->validate($customerId);

            $cart = $this->cartRepository->getActiveForCustomer(
                $customerId
            );

            if (count($cart->getAllVisibleItems()) === 0) {
                throw new LocalizedException(
                    __(
                        'Add at least one product to your cart before requesting a quote.'
                    )
                );
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage(
                $exception->getMessage()
            );

            return $this->redirectToCart();
        } catch (\Throwable) {
            $this->messageManager->addErrorMessage(
                __(
                    'We could not load your shopping cart. Please try again.'
                )
            );

            return $this->redirectToCart();
        }

        /** @var Page $resultPage */
        $resultPage = $this->pageFactory->create();

        $resultPage->getConfig()->getTitle()->set(
            __('Request a Quote')
        );

        return $resultPage;
    }

    private function redirectToLogin(): Redirect
    {
        $resultRedirect = $this->redirectFactory->create();

        return $resultRedirect->setPath(
            'customer/account/login'
        );
    }

    private function redirectToCart(): Redirect
    {
        $resultRedirect = $this->redirectFactory->create();

        return $resultRedirect->setPath(
            'checkout/cart'
        );
    }
}
