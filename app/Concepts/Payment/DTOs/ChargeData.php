<?php

namespace App\Concepts\Payment\DTOs;

use InvalidArgumentException;

final readonly class ChargeData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public int $amountInCents,
        public string $currency,
        public string $paymentMethodToken
    ) {
        if ($amountInCents <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        if (trim($currency) === '') {
            throw new InvalidArgumentException('Currency must not be blank.');
        }

        if (trim($paymentMethodToken) === '') {
            throw new InvalidArgumentException('Payment method token must not be blank.');
        }

    }
}
