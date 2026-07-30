<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Setup\Patch\Data;

use Magento\Customer\Api\Data\GroupInterfaceFactory;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class CreateBusinessCustomerGroup implements DataPatchInterface
{
    private const GROUP_CODE = 'Business Customer';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly GroupInterfaceFactory $groupFactory,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly GroupCollectionFactory $groupCollectionFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        try {
            if (!$this->groupExists()) {
                $group = $this->groupFactory->create();

                $group->setCode(self::GROUP_CODE);

                /*
                 * Tax class ID 3 is Magento's usual Retail Customer
                 * tax class in a default installation.
                 *
                 * For this BrewCraft learning project, the Business
                 * Customer group initially uses the same tax class.
                 */
                $group->setTaxClassId(3);

                $this->groupRepository->save($group);
            }
        } finally {
            $this->moduleDataSetup->getConnection()->endSetup();
        }

        return $this;
    }

    private function groupExists(): bool
    {
        $collection = $this->groupCollectionFactory->create();

        $collection->addFieldToFilter(
            'customer_group_code',
            self::GROUP_CODE
        );

        $collection->setPageSize(1);

        return (bool)$collection->getSize();
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}