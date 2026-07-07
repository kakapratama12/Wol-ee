<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIngredientRequest extends FormRequest
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
        $id = $this->route('ingredient')->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('ingredients', 'name')->ignore($id)],
            'unit_type' => ['required', Rule::in(['weight', 'volume', 'count'])],
            'base_unit' => ['required', 'string', 'max:20'],
            'unit_price' => ['required', 'numeric', 'gte:0', 'max:99999999999'],
            'minimum_stock' => ['required', 'numeric', 'gte:0'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ];
    }
}
