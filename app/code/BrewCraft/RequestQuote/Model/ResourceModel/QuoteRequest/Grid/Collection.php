<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequest\Grid;

use BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface;

class Collection extends SearchResult
{
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        string $mainTable = 'brewcraft_quote_request',
        string $resourceModel = QuoteRequestResource::class
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel
        );
    }

    protected function _initSelect(): static
    {
        parent::_initSelect();

        $this->getSelect()->joinLeft(
            [
                'customer' => $this->getTable(
                    'customer_entity'
                )
            ],
            'main_table.customer_id = customer.entity_id',
            [
                'customer_email' => 'customer.email'
            ]
        );

        $this->getSelect()->joinLeft(
            [
                'business_account' => $this->getTable(
                    'brewcraft_business_account'
                )
            ],
            'main_table.business_account_id = business_account.entity_id',
            [
                'company_name' => 'business_account.company_name'
            ]
        );

        /*
         * Tell Magento which SQL fields must be used when the
         * Admin applies filters to joined columns.
         */
        $this->addFilterToMap(
            'entity_id',
            'main_table.entity_id'
        );

        $this->addFilterToMap(
            'customer_id',
            'main_table.customer_id'
        );

        $this->addFilterToMap(
            'business_account_id',
            'main_table.business_account_id'
        );

        $this->addFilterToMap(
            'customer_email',
            'customer.email'
        );

        $this->addFilterToMap(
            'company_name',
            'business_account.company_name'
        );

        $this->addFilterToMap(
            'quote_number',
            'main_table.quote_number'
        );

        $this->addFilterToMap(
            'quote_name',
            'main_table.quote_name'
        );

        $this->addFilterToMap(
            'status',
            'main_table.status'
        );

        $this->addFilterToMap(
            'created_at',
            'main_table.created_at'
        );

        return $this;
    }
}
