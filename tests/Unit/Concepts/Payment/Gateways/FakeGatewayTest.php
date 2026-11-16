<?php

use App\Concepts\Payment\DTOs\ChargeData;
use App\Concepts\Payment\Gateways\FakeGateway;

it('records each charge and generates deterministic transaction identifiers', function () {
    $fakeGateway = new FakeGateway;

    $firstResult = $fakeGateway->charge(new ChargeData(
        amountInCents: 500,
        currency: 'usd',
        paymentMethodToken: 'pm_first',
    ));

    $secondResult = $fakeGateway->charge(new ChargeData(
        amountInCents: 900,
        currency: 'usd',
        paymentMethodToken: 'pm_second',
    ));

    expect($firstResult->transactionId)->toBe('fake_charge_1')
        ->and($secondResult->transactionId)->toBe('fake_charge_2')
        ->and($fakeGateway->charges())->toHaveCount(2);
});

it('fails only the next configured charge', function () {
    $fakeGateway = new FakeGateway;
    $fakeGateway->failNextCharge('Insufficient funds');

    $failedResult = $fakeGateway->charge(new ChargeData(
        amountInCents: 500,
        currency: 'usd',
        paymentMethodToken: 'pm_declined',
    ));

    $successfulResult = $fakeGateway->charge(new ChargeData(
        amountInCents: 500,
        currency: 'usd',
        paymentMethodToken: 'pm_approved',
    ));

    expect($failedResult->successful)->toBeFalse()
        ->and($failedResult->failureReason)->toBe('Insufficient funds')
        ->and($successfulResult->successful)->toBeTrue()
        ->and($successfulResult->transactionId)->toBe('fake_charge_2');
});
