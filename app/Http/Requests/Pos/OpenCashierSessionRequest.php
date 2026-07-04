<?php

namespace App\Http\Requests\Pos;

use App\Models\PosOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenCashierSessionRequest extends FormRequest
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

        // Allow pengelola (owner) from single-outlet businesses to open POS session
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
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ];
    }
}
