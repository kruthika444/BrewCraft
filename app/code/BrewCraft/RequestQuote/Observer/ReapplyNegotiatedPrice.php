<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;

class ReapplyNegotiatedPrice implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();

        if (
            !$quote instanceof Quote
            || !(int)$quote->getData('brewcraft_quote_request_id')
        ) {
            return;
        }

        $quote->setCouponCode('');

        foreach ($quote->getAllItems() as $item) {
            $negotiatedPrice = $item->getData('brewcraft_negotiated_price');
            $lockedQty = $item->getData('brewcraft_locked_qty');

            if ($negotiatedPrice === null || !is_numeric($negotiatedPrice)) {
                continue;
            }

            if ($lockedQty !== null && is_numeric($lockedQty)) {
                $item->setQty((float)$lockedQty);
            }

            $price = round((float)$negotiatedPrice, 4);

            $item->setCustomPrice($price);
            $item->setOriginalCustomPrice($price);
            $item->setNoDiscount(true);
            $item->getProduct()->setIsSuperMode(true);
        }
    }
}
