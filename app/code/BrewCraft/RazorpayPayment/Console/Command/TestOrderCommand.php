<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Console\Command;

use BrewCraft\RazorpayPayment\Gateway\Http\Client\RazorpayClient;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestOrderCommand extends Command
{
    private const OPTION_AMOUNT = 'amount';

    public function __construct(
        private readonly RazorpayClient $razorpayClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('brewcraft:razorpay:test-order');
        $this->setDescription(
            'Create a test Razorpay order from Magento.'
        );

        $this->addOption(
            self::OPTION_AMOUNT,
            null,
            InputOption::VALUE_OPTIONAL,
            'Order amount in INR.',
            '100.00'
        );

        parent::configure();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        try {
            $amount = (float) $input->getOption(
                self::OPTION_AMOUNT
            );

            if ($amount <= 0) {
                throw new LocalizedException(
                    __('Amount must be greater than zero.')
                );
            }

            /*
             * Razorpay expects INR amount in paise.
             *
             * Example:
             * ₹100.00 = 10000 paise
             */
            $amountInPaise = (int) round(
                $amount * 100
            );

            /*
             * Razorpay receipt must be unique.
             *
             * For this temporary CLI test we generate
             * a development reference.
             */
            $receipt = sprintf(
                'brewcraft_test_%s',
                time()
            );

            $output->writeln(
                '<info>Creating Razorpay test order...</info>'
            );

            $output->writeln(
                sprintf(
                    'Amount: INR %.2f (%d paise)',
                    $amount,
                    $amountInPaise
                )
            );

            $response = $this->razorpayClient->createOrder(
                $amountInPaise,
                'INR',
                $receipt,
                [
                    'source' => 'brewcraft_cli_test',
                ]
            );

            $output->writeln('');
            $output->writeln(
                '<info>Razorpay order created successfully.</info>'
            );

            $output->writeln(
                sprintf(
                    'Razorpay Order ID: %s',
                    $response['id'] ?? 'N/A'
                )
            );

            $output->writeln(
                sprintf(
                    'Status: %s',
                    $response['status'] ?? 'N/A'
                )
            );

            $output->writeln(
                sprintf(
                    'Amount: %s',
                    $response['amount'] ?? 'N/A'
                )
            );

            $output->writeln(
                sprintf(
                    'Amount Paid: %s',
                    $response['amount_paid'] ?? 'N/A'
                )
            );

            $output->writeln(
                sprintf(
                    'Amount Due: %s',
                    $response['amount_due'] ?? 'N/A'
                )
            );

            $output->writeln(
                sprintf(
                    'Currency: %s',
                    $response['currency'] ?? 'N/A'
                )
            );

            $output->writeln(
                sprintf(
                    'Receipt: %s',
                    $response['receipt'] ?? $receipt
                )
            );

            return Command::SUCCESS;
        } catch (LocalizedException $exception) {
            $output->writeln(
                sprintf(
                    '<error>%s</error>',
                    $exception->getMessage()
                )
            );

            return Command::FAILURE;
        } catch (\Throwable $exception) {
            $output->writeln(
                sprintf(
                    '<error>Unexpected error: %s</error>',
                    $exception->getMessage()
                )
            );

            return Command::FAILURE;
        }
    }
}
