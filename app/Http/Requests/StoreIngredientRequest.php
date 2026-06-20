<?php

namespace App\Http\Requests;

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
            'name' => ['required', 'string', 'max:255', Rule::unique('ingredients', 'name')],
            'item_type' => ['required', Rule::in(['raw_material', 'prep'])],
            'unit_type' => ['required', Rule::in(['gramasi', 'packaged'])],
            'base_unit' => ['required', 'string', 'max:20'],
            'unit_price' => ['required', 'numeric', 'gte:0'],
            'current_stock' => ['nullable', 'numeric', 'gte:0'],
            'minimum_stock' => ['required', 'numeric', 'gte:0'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ];
    }
}
