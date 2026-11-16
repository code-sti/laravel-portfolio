<?php

namespace App\Http\Controllers;

use App\Concepts\Payment\Actions\ChargePayment;
use App\Concepts\Payment\DTOs\ChargeData;
use App\Http\Requests\ChargePaymentRequest;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function store(
        ChargePaymentRequest $request,
        ChargePayment $chargePayment,
    ): JsonResponse {
        $chargeData = new ChargeData(
            amountInCents: $request->integer('amount'),
            currency: $request->string('currency')->toString(),
            paymentMethodToken: $request->string('payment_method_token')->toString(),
        );

        $paymentResult = $chargePayment->handle($chargeData);

        if (! $paymentResult->successful) {
            return response()->json([
                'message' => $paymentResult->failureReason,
            ], 402);
        }

        return response()->json([
            'payment_id' => $paymentResult->transactionId,
            'status' => 'succeeded',
        ], 201);
    }
}
