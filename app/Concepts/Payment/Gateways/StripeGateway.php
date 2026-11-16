<?php

namespace App\Concepts\Payment\Gateways;

use App\Concepts\Payment\Contracts\PaymentGateway;
use App\Concepts\Payment\DTOs\ChargeData;
use App\Concepts\Payment\DTOs\PaymentResult;
use Illuminate\Support\Facades\Http;
use LogicException;

final class StripeGateway implements PaymentGateway
{
    public function charge(ChargeData $chargeData): PaymentResult
    {
        $secret = config('services.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            throw new LogicException('Stripe is not configured.');
        }

        $response = Http::baseUrl((string) config('services.stripe.base_url'))
            ->withBasicAuth($secret, '')
            ->timeout(10)
            ->post('payment_intents', [
                'amount' => $chargeData->amountInCents,
                'currency' => $chargeData->currency,
                'payment_method' => $chargeData->paymentMethodToken,
                'confirm' => true,
            ]);

        if ($response->successful() && $response->json('status') === 'succeeded') {
            return PaymentResult::succeeded((string) $response->json('id'));
        }

        return PaymentResult::failed(
            (string) (
                $response->json('last_payment_error.message')
                ?? $response->json('error.message')
                ?? 'The payment was not approved.'
            ),
        );
    }
}
