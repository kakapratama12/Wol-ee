<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxSimulateRequest extends FormRequest
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
            'business_type' => ['required', Rule::in(['perorangan', 'cv', 'pt'])],
            'omset' => ['required', 'numeric', 'gte:0'],
            'cogs' => ['required', 'numeric', 'gte:0'],
            'expense' => ['required', 'numeric', 'gte:0'],
            'waste_percent' => ['required', 'numeric', 'between:0,100'],
        ];
    }
}
