<?php

namespace App\Providers;

use App\Concepts\Payment\Contracts\PaymentGateway;
use App\Concepts\Payment\Gateways\FakeGateway;
use App\Concepts\Payment\Gateways\StripeGateway;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function (Application $application): PaymentGateway {
            if ($application->environment('production')) {
                return $application->make(StripeGateway::class);
            }

            return $application->make(FakeGateway::class);
        });
    }
}
