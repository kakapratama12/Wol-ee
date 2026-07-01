<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashierSession;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CashierSessionService
{
    public function __construct(
        private readonly ProductAvailabilityService $availability,
    ) {}

    public function findOpenSession(User $user): ?CashierSession
    {
        return CashierSession::query()
            ->where('user_id', $user->id)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();
    }

    public function open(User $user, float $openingCash): CashierSession
    {
        if ($user->branch_id === null) {
            throw new InvalidArgumentException('Kasir belum di-assign ke cabang.');
        }

        $existing = $this->findOpenSession($user);

        if ($existing) {
            throw new InvalidArgumentException('Masih ada sesi kasir yang belum ditutup.');
        }

        $snapshot = $this->availability->buildOpeningSummary($user->tenant);

        return CashierSession::create([
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'opening_cash' => round($openingCash, 2),
            'opening_availability_snapshot' => $snapshot,
            'opened_at' => Carbon::now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function close(CashierSession $session, float $actualCash): array
    {
        if (! $session->isOpen()) {
            throw new InvalidArgumentException('Sesi kasir sudah ditutup.');
        }

        $expectedCash = round((float) $session->opening_cash + (float) $session->total_cash, 2);
        $variance = round($actualCash - $expectedCash, 2);

        $session->update([
            'actual_cash' => round($actualCash, 2),
            'variance' => $variance,
            'closed_at' => Carbon::now(),
        ]);

        return [
            'opening_cash' => (float) $session->opening_cash,
            'total_cash' => (float) $session->total_cash,
            'total_qris' => (float) $session->total_qris,
            'total_transfer' => (float) $session->total_transfer,
            'total_omset' => round(
                (float) $session->total_cash + (float) $session->total_qris + (float) $session->total_transfer,
                2,
            ),
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'variance' => $variance,
        ];
    }

    public function ensureDefaultBranch(Tenant $tenant): Branch
    {
        $branch = Branch::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($branch) {
            return $branch;
        }

        return Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cabang Utama',
            'is_active' => true,
        ]);
    }
}
