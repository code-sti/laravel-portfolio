<?php

namespace App\Concepts\Payment\Contracts;

use App\Concepts\Payment\DTOs\ChargeData;
use App\Concepts\Payment\DTOs\PaymentResult;

interface PaymentGateway
{
    public function charge(ChargeData $chargeData): PaymentResult;
}
