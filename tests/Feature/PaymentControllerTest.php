<?php

use App\Concepts\Payment\Contracts\PaymentGateway;
use App\Concepts\Payment\Gateways\FakeGateway;

it('returns 201 when a valid payment is approved', function () {
    $fakeGateway = new FakeGateway;
    $this->app->instance(PaymentGateway::class, $fakeGateway);

    $response = $this->postJson(route('payments.charge'), [
        'amount' => 1500,
        'currency' => 'usd',
        'payment_method_token' => 'pm_test_visa',
    ]);

    $response->assertCreated()
        ->assertExactJson([
            'payment_id' => 'fake_charge_1',
            'status' => 'succeeded',
        ]);

    expect($fakeGateway->charges())->toHaveCount(1);
});

it('returns 422 when payment input is invalid', function () {
    $response = $this->postJson(route('payments.charge'), [
        'amount' => 0,
        'currency' => 'eur',
        'payment_method_token' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'amount',
            'currency',
            'payment_method_token',
        ]);
});

it('returns 402 when the payment gateway declines the charge', function () {
    $fakeGateway = new FakeGateway;
    $fakeGateway->failNextCharge('Card declined');
    $this->app->instance(PaymentGateway::class, $fakeGateway);

    $response = $this->postJson(route('payments.charge'), [
        'amount' => 1500,
        'currency' => 'usd',
        'payment_method_token' => 'pm_test_declined',
    ]);

    $response->assertStatus(402)
        ->assertExactJson([
            'message' => 'Card declined',
        ]);
});
