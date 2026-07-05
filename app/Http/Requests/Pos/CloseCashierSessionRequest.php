<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashierSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        // Allow pengelola (owner) from single-outlet businesses to close POS session
        if ($user->isPengelola() && $user->tenant?->business_type === 'single') {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'closing_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
