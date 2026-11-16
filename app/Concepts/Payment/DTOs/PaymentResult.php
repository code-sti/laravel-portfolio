<?php

namespace App\Concepts\Payment\DTOs;

final readonly class PaymentResult
{
    private function __construct(
        public bool $successful,
        public ?string $transactionId,
        public ?string $failureReason,
    ) {}

    public static function succeeded(string $transactionId): self
    {
        return new self(
            successful: true,
            transactionId: $transactionId,
            failureReason: null,
        );
    }

    public static function failed(string $failureReason): self
    {
        return new self(
            successful: false,
            transactionId: null,
            failureReason: $failureReason,
        );
    }
}
