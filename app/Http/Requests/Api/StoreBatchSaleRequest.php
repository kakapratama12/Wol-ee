<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchSaleRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('items', []) as $index => $item) {
                if (empty($item['product_id']) && empty($item['product'])) {
                    $validator->errors()->add("items.{$index}.product", 'product_id atau product wajib diisi.');
                }
            }
        });
    }
}
