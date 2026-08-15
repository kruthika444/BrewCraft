<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class CreateProductSpecificationAttributes implements DataPatchInterface
{
    private const ATTRIBUTE_SET_ID = 4;

    private const ATTRIBUTE_GROUP = 'BrewCraft Specifications';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $eavSetup = $this->eavSetupFactory->create([
            'setup' => $this->moduleDataSetup
        ]);

        $attributes = $this->getAttributes();

        foreach ($attributes as $attributeCode => $config) {
            $attributeId = $eavSetup->getAttributeId(
                Product::ENTITY,
                $attributeCode
            );

            /*
             * Data patches can safely be rerun in development.
             * Do not attempt to recreate an existing attribute.
             */
            if (!$attributeId) {
                $eavSetup->addAttribute(
                    Product::ENTITY,
                    $attributeCode,
                    $config
                );
            }

            /*
             * Add attribute to our current product attribute set.
             */
            $eavSetup->addAttributeToSet(
                Product::ENTITY,
                self::ATTRIBUTE_SET_ID,
                self::ATTRIBUTE_GROUP,
                $attributeCode
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    private function getAttributes(): array
    {
        return [

            /*
             * Coffee-specific
             */

            'bean_type' => $this->textAttribute(
                'Bean Type'
            ),

            'roast_level' => $this->textAttribute(
                'Roast Level'
            ),

            'flavor_profile' => $this->textareaAttribute(
                'Flavor Profile'
            ),

            'brew_methods' => $this->textareaAttribute(
                'Brew Methods'
            ),


            /*
             * Equipment / machine specifications
             */

            'capacity' => $this->textAttribute(
                'Capacity'
            ),

            'material' => $this->textAttribute(
                'Material'
            ),

            'power' => $this->textAttribute(
                'Power'
            ),

            'voltage' => $this->textAttribute(
                'Voltage'
            ),

            'warranty' => $this->textAttribute(
                'Warranty'
            ),

            'grinder_type' => $this->textAttribute(
                'Grinder Type'
            ),

            'burr_type' => $this->textAttribute(
                'Burr Type'
            ),

            'water_tank_capacity' => $this->textAttribute(
                'Water Tank Capacity'
            ),

            'bean_hopper_capacity' => $this->textAttribute(
                'Bean Hopper Capacity'
            ),

            'pump_pressure' => $this->textAttribute(
                'Pump Pressure'
            ),

            'dimensions' => $this->textAttribute(
                'Dimensions'
            ),


            /*
             * PDP "What's Included"
             */

            'included_items' => $this->textareaAttribute(
                'Included Items'
            )
        ];
    }

    private function textAttribute(
        string $label
    ): array {
        return [
            'type' => 'varchar',
            'label' => $label,
            'input' => 'text',

            'required' => false,

            'visible' => true,

            'user_defined' => true,

            'system' => false,

            'global' => 1,

            'searchable' => false,

            'filterable' => false,

            'comparable' => false,

            'visible_on_front' => true,

            'used_in_product_listing' => false,

            'unique' => false
        ];
    }

    private function textareaAttribute(
        string $label
    ): array {
        return [
            'type' => 'text',
            'label' => $label,
            'input' => 'textarea',

            'required' => false,

            'visible' => true,

            'user_defined' => true,

            'system' => false,

            'global' => 1,

            'searchable' => false,

            'filterable' => false,

            'comparable' => false,

            'visible_on_front' => true,

            'used_in_product_listing' => false,

            'unique' => false
        ];
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
