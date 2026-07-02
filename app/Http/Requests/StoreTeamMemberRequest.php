<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPengelola() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in([User::ROLE_STAFF, User::ROLE_CASHIER])],
            'branch_id' => [
                Rule::requiredIf(fn () => $this->input('role') === User::ROLE_CASHIER),
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Kasir harus di-assign ke cabang.',
        ];
    }
}
