<?php

declare(strict_types=1);

namespace BrewCraft\RequestQuote\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;

class QuoteActions extends Column
{
    private const VIEW_URL_PATH = 'requestquote/quote/view';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct(
            $context,
            $uiComponentFactory,
            $components,
            $data
        );
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (
            !isset($dataSource['data']['items'])
            || !is_array($dataSource['data']['items'])
        ) {
            return $dataSource;
        }

        $columnName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $entityId = isset($item['entity_id'])
                ? (int)$item['entity_id']
                : 0;

            if ($entityId <= 0) {
                continue;
            }

            $item[$columnName]['view'] = [
                'href' => $this->urlBuilder->getUrl(
                    self::VIEW_URL_PATH,
                    [
                        'id' => $entityId
                    ]
                ),
                'label' => __('View'),
                'hidden' => false
            ];
        }

        return $dataSource;
    }
}
