<?php

namespace App\Http\Requests;

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
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')],
            'unit' => ['required', 'string', 'max:20'],
'selling_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'recipe_type' => ['required', Rule::in(['unit', 'batch'])],
            'estimated_yield_per_batch' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'is_prep' => ['nullable', 'boolean'],
        ];
    }
}
