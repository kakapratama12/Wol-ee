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
                'required', 'integer',
                Rule::exists('partners', 'id')->where('type', Partner::TYPE_CUSTOMER),
            ],
            'po_number' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,outstanding'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'due_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.description' => ['required_with:items', 'string'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'fees' => ['nullable', 'array', 'max:20'],
            'fees.*.name' => ['required_with:fees', 'string'],
            'fees.*.type' => ['required_with:fees', 'in:fixed,percentage'],
            'fees.*.value' => ['required_with:fees', 'numeric', 'min:0'],
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
