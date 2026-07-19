<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Console;

use BrewCraft\ErpIntegration\Model\Api\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BrewCraft\ErpIntegration\Model\Service\ProductService;
use BrewCraft\ErpIntegration\Model\Service\ProductImportService;
use Magento\Framework\Console\Cli;
use Magento\Framework\App\State;
use BrewCraft\ErpIntegration\Logger\Logger;
use BrewCraft\ErpIntegration\Model\Service\CategoryImportService;

class TestConnection extends Command
{
    protected $productImportService;
    protected $productService;
    protected $state;
    protected $logger;
    protected $categoryImportService;

    public function __construct(
        ProductService $productService,
        ProductImportService $productImportService,
        State $state,
        Logger $logger,
        CategoryImportService $categoryImportService
    ) {
        parent::__construct();

        $this->productService = $productService;
        $this->productImportService = $productImportService;
        $this->state = $state;
        $this->logger = $logger;
        $this->categoryImportService = $categoryImportService;
    }

    protected function configure()
    {
        $this->setName('brewcraft:erp:test');
        $this->setDescription('Test ERP Connection');

        parent::configure();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {

        $output->writeln('<info>Connecting to ERP...</info>');
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Throwable $exception) {
            $this->logger->critical($exception);

            throw $exception;
        }

        try {
            $output->writeln('<info>Importing Categories...</info>');

            $this->categoryImportService->import();

            $output->writeln('<info>Categories Imported.</info>');

            $output->writeln('<info>Importing Products...</info>');

            $products = $this->productService->getProducts();

            $result = $this->productImportService->import($products);

            $output->writeln('<info>Products Imported.</info>');

            $output->writeln('<info>Synchronization Summary</info>');
            $output->writeln('--------------------------------');
            $output->writeln('Created : ' . $result['created']);
            $output->writeln('Updated : ' . $result['updated']);
            $output->writeln('Failed  : ' . $result['failed']);
            $output->writeln('Total   : ' . count($products));
        } catch (\Throwable $e) {

            $output->writeln(
                sprintf(
                    '<error>%s</error>',
                    $e->getMessage()
                )
            );

            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }
}
