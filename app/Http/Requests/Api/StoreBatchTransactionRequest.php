<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchTransactionRequest extends FormRequest
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
            'items.*.ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'items.*.ingredient' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
            'items.*.total' => ['nullable', 'numeric', 'gte:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('items', []) as $index => $item) {
                if (empty($item['ingredient_id']) && empty($item['ingredient'])) {
                    $validator->errors()->add("items.{$index}.ingredient", 'ingredient_id atau ingredient wajib diisi.');
                }
                if (empty($item['unit_price']) && empty($item['total'])) {
                    $validator->errors()->add("items.{$index}.unit_price", 'unit_price atau total wajib diisi.');
                }
            }
        });
    }
}
