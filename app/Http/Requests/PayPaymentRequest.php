<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
