<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
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
            'ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'ingredient' => ['nullable', 'string', 'max:255'],
            // quantity dalam base_unit ingredient (mis. gram, ml)
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'unit_price' => ['nullable', 'numeric', 'gte:0', 'max:99999999999'],
            'total' => ['nullable', 'numeric', 'gte:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('ingredient_id') && ! $this->filled('ingredient')) {
                $validator->errors()->add('ingredient', 'ingredient_id atau ingredient wajib diisi.');
            }
            if (! $this->filled('unit_price') && ! $this->filled('total')) {
                $validator->errors()->add('unit_price', 'unit_price atau total wajib diisi.');
            }
        });
    }
}
