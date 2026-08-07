<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Block\Cart;

use BrewCraft\RequestQuote\Model\Service\BusinessCustomerEligibilityService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class RequestQuote extends Template
{
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly BusinessCustomerEligibilityService $eligibilityService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function canRequestQuote(): bool
    {
        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }

        $customerId = (int)$this->customerSession->getCustomerId();

        if (!$this->eligibilityService->isEligible($customerId)) {
            return false;
        }

        try {
            $cart = $this->cartRepository->getActiveForCustomer($customerId);

            if ((int)$cart->getData('brewcraft_quote_request_id') > 0) {
                return false;
            }

            return count($cart->getAllVisibleItems()) > 0;
        } catch (\Throwable $exception) {
            $this->logger->debug(
                'Unable to determine Request Quote cart availability.',
                [
                    'customer_id' => $customerId,
                    'exception' => $exception->getMessage()
                ]
            );

            return false;
        }
    }

    public function getRequestQuoteUrl(): string
    {
        return $this->getUrl('requestquote/request/create');
    }
}
