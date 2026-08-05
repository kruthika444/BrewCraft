<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model;

use Magento\Framework\Model\AbstractModel;

class QuoteRequestItem extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(
            \BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequestItem::class
        );
    }

    public function getQuoteRequestId(): int
    {
        return (int)$this->getData('quote_request_id');
    }

    public function getProductId(): ?int
    {
        $productId = $this->getData('product_id');

        return $productId !== null
            ? (int)$productId
            : null;
    }

    public function getSku(): string
    {
        return (string)$this->getData('sku');
    }

    public function getRequestedQty(): float
    {
        return (float)$this->getData('requested_qty');
    }
}
