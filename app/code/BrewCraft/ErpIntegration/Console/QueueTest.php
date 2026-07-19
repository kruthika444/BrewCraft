<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Console;

use BrewCraft\ErpIntegration\Model\Queue\Publisher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueueTest extends Command
{
    public function __construct(
        private readonly Publisher $publisher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('brewcraft:queue:test');
        $this->setDescription('Publish Queue Message');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {

        $this->publisher->publish(
            'Hello Kruthi!'
        );
        

        $output->writeln(
            '<info>Message Published.</info>'
        );


        return self::SUCCESS;
    }
}
