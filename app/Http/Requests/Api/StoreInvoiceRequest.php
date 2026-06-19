<?php

namespace App\Http\Requests\Api;

use App\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
            'partner_id' => [
                'required',
                'integer',
                Rule::exists('partners', 'id')->where('type', Partner::TYPE_CUSTOMER),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'due_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'partner_id.exists' => 'Partner customer tidak ditemukan.',
        ];
    }
}
