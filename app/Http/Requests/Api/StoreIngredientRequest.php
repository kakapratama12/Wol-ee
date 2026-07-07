<?php

namespace App\Http\Requests\Api;

use App\Models\Ingredient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngredientRequest extends FormRequest
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
                Rule::unique('ingredients', 'name')->where(fn ($query) => $query->where('tenant_id', $this->user()?->tenant_id)),
            ],
            'item_type' => [
                'nullable',
                Rule::in([Ingredient::ITEM_RAW_MATERIAL, Ingredient::ITEM_PREP, Ingredient::ITEM_FINISHED_GOODS]),
            ],
            'unit_type' => ['required', Rule::in(['weight', 'volume', 'count'])],
            'base_unit' => ['required', 'string', 'max:50'],
            'unit_price' => ['required', 'numeric', 'gte:0', 'max:99999999999'],
            'minimum_stock' => ['nullable', 'numeric', 'gte:0', 'max:9999999'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ];
    }
}
