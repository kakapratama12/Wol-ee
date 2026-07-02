<?php

namespace App\Services;

use App\Events\CashierSessionClosed;
use App\Models\CashierSession;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\Sale;
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
        if ($user->outlet_id === null) {
            throw new InvalidArgumentException('Kasir belum di-assign ke outlet.');
        }

        $existing = $this->findOpenSession($user);

        if ($existing) {
            throw new InvalidArgumentException('Masih ada sesi kasir yang belum ditutup.');
        }

        $snapshot = $this->availability->buildOpeningSummary($user->tenant, $user->outlet_id);

        return CashierSession::create([
            'tenant_id' => $user->tenant_id,
            'outlet_id' => $user->outlet_id,
            'user_id' => $user->id,
            'opening_cash' => round($openingCash, 2),
            'opening_availability_snapshot' => $snapshot,
            'opened_at' => Carbon::now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function close(CashierSession $session, float $actualCash, ?string $closingNote = null): array
    {
        if (! $session->isOpen()) {
            throw new InvalidArgumentException('Sesi kasir sudah ditutup.');
        }

        $expectedCash = round((float) $session->opening_cash + (float) $session->total_cash, 2);
        $variance = round($actualCash - $expectedCash, 2);

        $session->update([
            'actual_cash' => round($actualCash, 2),
            'variance' => $variance,
            'closing_note' => $closingNote,
            'closed_at' => Carbon::now(),
        ]);

        $summary = [
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
            'sales_summary' => $this->salesSummary($session),
        ];

        CashierSessionClosed::dispatch($session->fresh(['outlet', 'user']), $summary);

        return $summary;
    }

    /**
     * @return list<array{product: string, quantity: int, revenue: float}>
     */
    public function salesSummary(CashierSession $session): array
    {
        $orderIds = PosOrder::query()
            ->where('cashier_session_id', $session->id)
            ->where('status', PosOrder::STATUS_COMPLETED)
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            return [];
        }

        return Sale::query()
            ->active()
            ->whereIn('pos_order_id', $orderIds)
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->selectRaw('products.name as product, SUM(sales.quantity) as quantity, SUM(sales.revenue) as revenue')
            ->groupBy('products.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'product' => $row->product,
                'quantity' => (int) $row->quantity,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->values()
            ->all();
    }

    public function ensureDefaultOutlet(Tenant $tenant): Outlet
    {
        $outlet = Outlet::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($outlet) {
            return $outlet;
        }

        return Outlet::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Outlet Utama',
            'is_active' => true,
        ]);
    }
}
