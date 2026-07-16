<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Console;

use BrewCraft\ErpIntegration\Model\Service\InventoryImportService;
use BrewCraft\ErpIntegration\Model\Service\InventoryService;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;


class InventorySync extends Command
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryImportService $inventoryImportService,
        private readonly State $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('brewcraft:erp:inventory:test');
        $this->setDescription('Synchronize Inventory from ERP');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {

        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Exception $exception) {
        }

        $output->writeln('<info>Fetching Inventory...</info>');

        $inventory = $this->inventoryService->getInventory();

        $result = $this->inventoryImportService->import($inventory);

        $output->writeln('');
        $output->writeln('<info>Synchronization Summary</info>');
        $output->writeln('--------------------------------');
        $output->writeln('Updated : ' . $result['updated']);
        $output->writeln('Failed  : ' . $result['failed']);
        $output->writeln('Total   : ' . count($inventory));

        return Cli::RETURN_SUCCESS;
    }
}
