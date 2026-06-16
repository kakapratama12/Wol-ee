<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
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
            'current_stock' => ['required', 'numeric', 'gte:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
