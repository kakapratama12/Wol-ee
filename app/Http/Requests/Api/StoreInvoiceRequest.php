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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasItems = ! empty($this->items) && is_array($this->items);
            $hasAmount = ! empty($this->amount) && (float) $this->amount > 0;

            if (! $hasItems && ! $hasAmount) {
                $validator->errors()->add('amount', 'Masukkan nominal invoice atau tambahkan rincian item.');
            }
        });
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
