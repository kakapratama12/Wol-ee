<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $outlet = $user->outlet;

        if (! $outlet) {
            return redirect()->route('pos.landing')->with('error', 'Anda belum di-assign ke outlet.');
        }

        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        // Today's sales for this outlet
        $todaySales = Sale::query()
            ->active()
            ->with('product:id,name')
            ->where('outlet_id', $outlet->id)
            ->whereBetween('occurred_at', [$todayStart->toIso8601String(), $todayEnd->toIso8601String()])
            ->latest('occurred_at')
            ->get();

        $todayRevenue = round((float) $todaySales->sum('revenue'), 2);
        $todayTransactions = $todaySales->count();
        $todayItemsSold = (int) $todaySales->sum('quantity');

        $recentSales = $todaySales
            ->take(20)
            ->map(fn (Sale $s) => [
                'id' => $s->id,
                'product' => $s->product?->name,
                'quantity' => $s->quantity,
                'revenue' => (float) $s->revenue,
                'occurred_at' => $s->occurred_at?->toIso8601String(),
            ]);

        return Inertia::render('Pos/Today', [
            'todayRevenue' => $todayRevenue,
            'todayTransactions' => $todayTransactions,
            'todayItemsSold' => $todayItemsSold,
            'recentSales' => $recentSales,
            'outlet' => $outlet->name,
        ]);
    }
}
