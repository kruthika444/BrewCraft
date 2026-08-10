<?php

declare(strict_types=1);

namespace BrewCraft\RazorpayPayment\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;

class Method extends AbstractMethod
{
    public const CODE = 'brewcraft_razorpay';

    protected $_code = self::CODE;

    protected $_isGateway = true;

    protected $_canAuthorize = false;

    protected $_canCapture = false;

    protected $_canRefund = false;

    protected $_canVoid = false;

    protected $_canUseCheckout = true;

    protected $_canUseInternal = false;
}
