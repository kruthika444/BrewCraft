<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Controller\Payment;

use BrewCraft\RazorpayPayment\Model\Service\VerifyAndPlaceOrderService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class Verify implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly VerifyAndPlaceOrderService $verifyAndPlaceOrderService,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        $payload = json_decode(
            (string) $this->request->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        try {
            $razorpayPaymentId = trim(
                (string) (
                    $payload['razorpay_payment_id']
                    ?? ''
                )
            );

            $razorpayOrderId = trim(
                (string) (
                    $payload['razorpay_order_id']
                    ?? ''
                )
            );

            $razorpaySignature = trim(
                (string) (
                    $payload['razorpay_signature']
                    ?? ''
                )
            );

            if (
                $razorpayPaymentId === ''
                || $razorpayOrderId === ''
                || $razorpaySignature === ''
            ) {
                throw new LocalizedException(
                    __('Required Razorpay payment information is missing.')
                );
            }

            $order = $this
                ->verifyAndPlaceOrderService
                ->execute(
                    $razorpayPaymentId,
                    $razorpayOrderId,
                    $razorpaySignature
                );

            return $result->setData([
                'success' => true,
                'order' => $order,
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
                'Unexpected Razorpay verification/order placement error.',
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
                        'Unable to complete the Razorpay order.'
                    ),
                ]);
        }
    }
}
