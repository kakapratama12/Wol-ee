<?php

namespace App\Http\Requests\Api;

use App\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends FormRequest
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
        $partnerId = $this->route('partner')?->id ?? $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('partners', 'name')->ignore($partnerId),
            ],
            'type' => ['sometimes', 'required', Rule::in([Partner::TYPE_CUSTOMER, Partner::TYPE_SUPPLIER])],
            'contact' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
