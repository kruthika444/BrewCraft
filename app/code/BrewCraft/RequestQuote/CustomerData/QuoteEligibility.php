<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\CustomerData;

use BrewCraft\RequestQuote\Model\Service\BusinessCustomerEligibilityService;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\UrlInterface;

class QuoteEligibility implements SectionSourceInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly BusinessCustomerEligibilityService $eligibilityService,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Return private customer-specific data for the mini-cart.
     *
     * @return array<string, mixed>
     */
    public function getSectionData(): array
    {
        $customerId = (int)$this
            ->customerSession
            ->getCustomerId();

        if (
            !$this->customerSession->isLoggedIn()
            || $customerId <= 0
        ) {
            return $this->getUnavailableData();
        }

        try {
            $this->eligibilityService->validate(
                $customerId
            );
        } catch (\Throwable) {
            return $this->getUnavailableData();
        }

        return [
            'can_request_quote' => true,
            'request_quote_url' => $this->urlBuilder->getUrl(
                'requestquote/request/create'
            )
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getUnavailableData(): array
    {
        return [
            'can_request_quote' => false,
            'request_quote_url' => ''
        ];
    }
}
