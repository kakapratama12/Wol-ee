<?php

namespace App\Events;

use App\Models\CashierSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CashierSessionClosed
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public readonly CashierSession $session,
        public readonly array $summary,
    ) {}
}
