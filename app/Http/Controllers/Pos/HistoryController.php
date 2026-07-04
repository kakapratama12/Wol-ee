<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $outletId = $user->outlet_id;

        // Default: 7 hari terakhir
        $from = $request->input('from', now()->subDays(7)->toDateString());
        $to = $request->input('to', now()->toDateString());
        $type = $request->input('type', 'all'); // all | purchase | sale

        $query = collect();

        // Pembelian bahan (stock movements type=purchase untuk outlet ini)
        if ($type === 'all' || $type === 'purchase') {
            $movements = StockMovement::query()
                ->where('outlet_id', $outletId)
                ->where('type', 'purchase')
                ->whereDate('occurred_at', '>=', $from)
                ->whereDate('occurred_at', '<=', $to)
                ->with('ingredient:id,name,base_unit')
                ->latest('occurred_at')
                ->get();

            // Load transaction total for each purchase
            $transactionIds = $movements->pluck('source_id')->filter()->unique();
            $transactions = Transaction::whereIn('id', $transactionIds)
                ->pluck('total', 'id');

            $purchases = $movements->map(fn ($m) => [
                'id' => $m->id,
                'type' => 'purchase',
                'name' => $m->ingredient?->name ?? '-',
                'detail' => number_format($m->quantity, 2, ',', '.') . ' ' . ($m->ingredient?->base_unit ?? ''),
                'amount' => $transactions->get($m->source_id, 0),
                'occurred_at' => $m->occurred_at,
            ]);

            $query = $query->concat($purchases);
        }

        // Penjualan produk (sales untuk outlet ini)
        if ($type === 'all' || $type === 'sale') {
            $sales = Sale::query()
                ->where('outlet_id', $outletId)
                ->whereDate('occurred_at', '>=', $from)
                ->whereDate('occurred_at', '<=', $to)
                ->with('product:id,name')
                ->latest('occurred_at')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'type' => 'sale',
                    'name' => $s->product?->name ?? '-',
                    'detail' => $s->quantity . 'x',
                    'amount' => $s->revenue,
                    'occurred_at' => $s->occurred_at,
                ]);

            $query = $query->concat($sales);
        }

        // Sort by date desc
        $history = $query->sortByDesc('occurred_at')->values();

        return Inertia::render('Pos/History', [
            'history' => $history,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'type' => $type,
            ],
            'outletName' => $user->outlet?->name ?? '-',
        ]);
    }
}
