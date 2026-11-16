<?php

use App\Concepts\Payment\Actions\ChargePayment;
use App\Concepts\Payment\DTOs\ChargeData;
use App\Concepts\Payment\Gateways\FakeGateway;

it('returns the successful result from the injected gateway', function () {
    $fakeGateway = new FakeGateway;
    $chargePayment = new ChargePayment($fakeGateway);
    $chargeData = new ChargeData(
        amountInCents: 1500,
        currency: 'usd',
        paymentMethodToken: 'pm_test_visa',
    );

    $paymentResult = $chargePayment->handle($chargeData);

    expect($paymentResult->successful)->toBeTrue()
        ->and($paymentResult->transactionId)->toBe('fake_charge_1')
        ->and($fakeGateway->charges())->toHaveCount(1)
        ->and($fakeGateway->charges()[0])->toBe($chargeData);
});

it('returns a declined result when the injected gateway declines the charge', function () {
    $fakeGateway = new FakeGateway;
    $fakeGateway->failNextCharge('Card declined');

    $chargePayment = new ChargePayment($fakeGateway);

    $paymentResult = $chargePayment->handle(new ChargeData(
        amountInCents: 1500,
        currency: 'usd',
        paymentMethodToken: 'pm_test_declined',
    ));

    expect($paymentResult->successful)->toBeFalse()
        ->and($paymentResult->failureReason)->toBe('Card declined');
});
