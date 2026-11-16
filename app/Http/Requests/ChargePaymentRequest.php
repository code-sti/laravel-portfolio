<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChargePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:50'],
            'currency' => ['required', 'string', Rule::in(['usd'])],
            'payment_method_token' => ['required', 'string', 'max:255'],
        ];
    }
}
