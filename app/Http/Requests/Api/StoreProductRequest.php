<?php

namespace App\Http\Requests\Api;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')->where(fn ($query) => $query->where('tenant_id', $this->user()?->tenant_id)),
            ],
            'unit' => ['required', 'string', 'max:50'],
            'selling_price' => ['required', 'numeric', 'gte:0', 'max:99999999999'],
            'recipe_type' => ['nullable', Rule::in([Product::RECIPE_UNIT, Product::RECIPE_BATCH])],
            'estimated_yield_per_batch' => ['nullable', 'integer', 'gte:1', 'max:9999'],
            'is_prep' => ['nullable', 'boolean'],
        ];
    }
}
