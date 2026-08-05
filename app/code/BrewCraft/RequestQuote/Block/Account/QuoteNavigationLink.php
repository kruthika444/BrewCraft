<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Block\Account;

use BrewCraft\RequestQuote\Model\Service\BusinessCustomerEligibilityService;
use Magento\Customer\Block\Account\SortLink;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template\Context;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\DefaultPathInterface;

class QuoteNavigationLink extends SortLink
{
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        private readonly CustomerSession $customerSession,
        private readonly BusinessCustomerEligibilityService $eligibilityService,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    /**
     * Render the navigation link only for an approved Business Customer.
     */
    protected function _toHtml(): string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        $customerId = (int)$this->customerSession->getCustomerId();

        if ($customerId <= 0) {
            return '';
        }

        try {
            if (!$this->eligibilityService->isEligible($customerId)) {
                return '';
            }
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Unable to determine quote-navigation visibility.',
                [
                    'customer_id' => $customerId,
                    'exception' => $exception->getMessage()
                ]
            );

            return '';
        }

        return parent::_toHtml();
    }
}
