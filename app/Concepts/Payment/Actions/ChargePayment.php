<?php

namespace App\Concepts\Payment\Actions;

use App\Concepts\Payment\Contracts\PaymentGateway;
use App\Concepts\Payment\DTOs\ChargeData;
use App\Concepts\Payment\DTOs\PaymentResult;

final class ChargePayment
{
    public function __construct(
        private PaymentGateway $paymentGateway,
    ) {}

    public function handle(ChargeData $chargeData): PaymentResult
    {
        return $this->paymentGateway->charge($chargeData);
    }
}
