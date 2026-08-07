<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Plugin\Checkout;

use Magento\Checkout\Model\Cart;
use Magento\Framework\Exception\LocalizedException;

class ControlledCartPlugin
{
    public function beforeUpdateItems(Cart $subject, array $data): array
    {
        $quote = $subject->getQuote();

        if (!(int)$quote->getData('brewcraft_quote_request_id')) {
            return [$data];
        }

        foreach ($quote->getAllVisibleItems() as $item) {
            $itemId = (int)$item->getId();
            $lockedQty = $item->getData('brewcraft_locked_qty');

            if ($lockedQty === null || !isset($data[$itemId]['qty'])) {
                continue;
            }

            if (abs((float)$data[$itemId]['qty'] - (float)$lockedQty) > 0.0001) {
                throw new LocalizedException(
                    __('The quantity for %1 is fixed by the accepted quote and cannot be changed.', $item->getName())
                );
            }
        }

        return [$data];
    }

    public function beforeRemoveItem(Cart $subject, int $itemId): array
    {
        $quote = $subject->getQuote();

        if (!(int)$quote->getData('brewcraft_quote_request_id')) {
            return [$itemId];
        }

        $item = $quote->getItemById($itemId);

        if ($item && $item->getData('brewcraft_quote_request_item_id')) {
            throw new LocalizedException(
                __('Products from an accepted quote cannot be removed individually.')
            );
        }

        return [$itemId];
    }

    public function beforeAddProduct(
        Cart $subject,
        mixed $productInfo,
        mixed $requestInfo = null
    ): array {
        if ((int)$subject->getQuote()->getData('brewcraft_quote_request_id')) {
            throw new LocalizedException(
                __('Additional products cannot be added while an accepted quote is active in the cart.')
            );
        }

        return [$productInfo, $requestInfo];
    }
}
