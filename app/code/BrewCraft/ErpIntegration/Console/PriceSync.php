<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Console;

use BrewCraft\ErpIntegration\Model\Service\PriceImportService;
use BrewCraft\ErpIntegration\Model\Service\PriceService;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;


class PriceSync extends Command
{
    public function __construct(
        private readonly PriceService $priceService,
        private readonly PriceImportService $priceImportService,
        private readonly State $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('brewcraft:erp:prices:test');
        $this->setDescription('Synchronize Prices from ERP');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {

        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Exception $exception) {
        }

        $output->writeln('<info>Fetching Prices...</info>');

        $prices = $this->priceService->getPrices();

        $result = $this->priceImportService->import($prices);

        $output->writeln('');
        $output->writeln('<info>Synchronization Summary</info>');
        $output->writeln('--------------------------------');
        $output->writeln('Updated : ' . $result['updated']);
        $output->writeln('Failed  : ' . $result['failed']);
        $output->writeln('Total   : ' . count($prices));

        return Cli::RETURN_SUCCESS;
    }
}
