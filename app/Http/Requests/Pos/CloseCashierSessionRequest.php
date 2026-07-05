<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashierSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
         = ->user();
        if (! ) {
            return false;
        }

        if (->isStaff()) {
            return true;
        }

        // Allow pengelola (owner) from single-outlet businesses to close POS session
        if (->isPengelola() && ->tenant?->business_type === 'single') {
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
