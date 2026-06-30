<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
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
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:99999999999'],
            'due_date' => ['sometimes', 'required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
