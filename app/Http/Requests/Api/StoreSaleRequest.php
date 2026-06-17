<?php

namespace App\Http\Requests\Api;

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
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'gt:0'],
            'unit_price' => ['nullable', 'numeric', 'gte:0'],
            'total' => ['nullable', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('product_id') && ! $this->filled('product')) {
                $validator->errors()->add('product', 'product_id atau product wajib diisi.');
            }
        });
    }
}
