<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999999'],
            'idempotency_key' => ['nullable', 'string', 'max:36'],
            'unit_price' => ['nullable', 'numeric', 'gte:0', 'max:99999999999'],
            'note' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
