<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
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
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            // quantity dalam base_unit ingredient
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'idempotency_key' => ['nullable', 'string', 'max:36'],
            'total' => ['required', 'numeric', 'gte:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
            // AP fields
            'bayar_nanti' => ['nullable', 'boolean'],
            'partner_id' => ['required_if:bayar_nanti,true', 'nullable', 'exists:partners,id'],
            'due_date' => ['required_if:bayar_nanti,true', 'nullable', 'date'],
        ];
    }
}
