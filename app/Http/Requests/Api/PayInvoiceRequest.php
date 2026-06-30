<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PayInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
'amount' => ['required', 'numeric', 'min:0', 'max:99999999999']
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
