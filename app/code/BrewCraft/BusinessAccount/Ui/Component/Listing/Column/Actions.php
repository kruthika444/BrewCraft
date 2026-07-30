<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class Actions extends Column
{
    private const VIEW_URL_PATH =
        'businessaccount/application/view';

    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
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

    public function prepareDataSource(
        array $dataSource
    ): array {
        if (
            !isset($dataSource['data']['items'])
            || !is_array($dataSource['data']['items'])
        ) {
            return $dataSource;
        }

        $columnName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $entityId = (int)($item['entity_id'] ?? 0);

            if ($entityId <= 0) {
                continue;
            }

            $item[$columnName]['view'] = [
                'href' => $this->urlBuilder->getUrl(
                    self::VIEW_URL_PATH,
                    ['entity_id' => $entityId]
                ),
                'label' => __('View'),
                'hidden' => false
            ];
        }

        unset($item);

        return $dataSource;
    }
}