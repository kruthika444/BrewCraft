<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\CustomerData;

use BrewCraft\RequestQuote\Model\Service\BusinessCustomerEligibilityService;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class QuoteEligibility implements SectionSourceInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly BusinessCustomerEligibilityService $eligibilityService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function getSectionData(): array
    {
        $customerId = (int)$this->customerSession->getCustomerId();

        if (
            !$this->customerSession->isLoggedIn()
            || $customerId <= 0
        ) {
            return $this->getUnavailableData();
        }

        try {
            $this->eligibilityService->validate($customerId);
            $cart = $this->cartRepository->getActiveForCustomer($customerId);

            if ((int)$cart->getData('brewcraft_quote_request_id') > 0) {
                return [
                    'can_request_quote' => false,
                    'is_accepted_quote_cart' => true,
                    'request_quote_url' => ''
                ];
            }
        } catch (\Throwable) {
            return $this->getUnavailableData();
        }

        return [
            'can_request_quote' => true,
            'is_accepted_quote_cart' => false,
            'request_quote_url' => $this->urlBuilder->getUrl(
                'requestquote/request/create'
            )
        ];
    }

    private function getUnavailableData(): array
    {
        return [
            'can_request_quote' => false,
            'is_accepted_quote_cart' => false,
            'request_quote_url' => ''
        ];
    }
}
