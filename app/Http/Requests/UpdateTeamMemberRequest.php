<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
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
        $member = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in([User::ROLE_STAFF, User::ROLE_CASHIER])],
            'branch_id' => [
                Rule::requiredIf(fn () => $this->input('role') === User::ROLE_CASHIER),
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
        ];
    }

    public function authorizeMember(): void
    {
        $member = $this->route('user');
        $tenantId = $this->user()?->tenant_id;

        abort_unless($member && $member->tenant_id === $tenantId, 403);
        abort_if($member->isPengelola() || $member->isSuperAdmin(), 403);
    }
}
