<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Controller\Payment;

use BrewCraft\RazorpayPayment\Model\Config;
use BrewCraft\RazorpayPayment\Model\Service\CreateCheckoutOrderService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class CreateOrder implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly CreateCheckoutOrderService $createOrderService,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        try {
            if (!$this->config->isActive()) {
                throw new LocalizedException(
                    __('Razorpay payment is currently unavailable.')
                );
            }

            $orderData =
                $this->createOrderService->execute();

            return $result->setData([
                'success' => true,
                'order' => $orderData,
            ]);
        } catch (LocalizedException $exception) {
            return $result
                ->setHttpResponseCode(400)
                ->setData([
                    'success' => false,
                    'message' =>
                        $exception->getMessage(),
                ]);
        } catch (\Throwable $exception) {
            $this->logger->critical(
                'Unable to create checkout Razorpay order.',
                [
                    'exception' =>
                        $exception->getMessage(),
                ]
            );

            return $result
                ->setHttpResponseCode(500)
                ->setData([
                    'success' => false,
                    'message' => __(
                        'Unable to initialise Razorpay payment.'
                    ),
                ]);
        }
    }
}
