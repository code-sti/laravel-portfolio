<?php

namespace App\Concepts\Payment\Gateways;

use App\Concepts\Payment\Contracts\PaymentGateway;
use App\Concepts\Payment\DTOs\ChargeData;
use App\Concepts\Payment\DTOs\PaymentResult;
use Override;

final class FakeGateway implements PaymentGateway
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    private array $charges = [];

    private ?string $nextFailureReason = null;

    #[Override]
    public function charge(ChargeData $chargeData): PaymentResult
    {
        $this->charges[] = $chargeData;

        if ($this->nextFailureReason !== null) {
            $failureReason = $this->nextFailureReason;
            $this->nextFailureReason = null;

            return PaymentResult::failed($failureReason);
        }

        return PaymentResult::succeeded('fake_charge_'.count($this->charges));
    }

    public function failNextCharge(string $failureReason): void
    {
        $this->nextFailureReason = $failureReason;
    }

    /**
     * @return list<ChargeData>
     */
    public function charges(): array
    {
        return $this->charges;
    }
}
