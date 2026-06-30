<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecipeRequest extends FormRequest
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
        $productId = $this->route('product')?->id ?? $this->route('product');
        $product = $productId ? Product::find($productId) : null;

        // If the product is a prep item, only allow raw_material ingredients
        $ingredientRule = ($product && $product->is_prep)
            ? ['required', 'integer', Rule::exists('ingredients', 'id')->where('item_type', 'raw_material')]
            : ['required', 'integer', 'exists:ingredients,id'];

        return [
            'items' => ['present', 'array', 'max:50'],
            'items.*.ingredient_id' => $ingredientRule,
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'estimated_yield_per_batch' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
