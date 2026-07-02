<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Models\CashierSession;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TodayController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $session = CashierSession::where('user_id', $user->id)
            ->whereDate('opened_at', $today)
            ->whereNull('closed_at')
            ->first();

        $orders = PosOrder::where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->with('sales.product')
            ->get();

        $summary = [
            'total_orders' => $orders->count(),
            'total_revenue' => (float) $orders->sum('total'),
            'total_items' => $orders->sum(fn ($o) => $o->sales->sum('quantity')),
            'tunai' => (float) $orders->where('payment_method', 'tunai')->sum('total'),
            'qris' => (float) $orders->where('payment_method', 'qris')->sum('total'),
            'transfer' => (float) $orders->where('payment_method', 'transfer')->sum('total'),
        ];

        $recent = $orders->map(fn ($o) => [
            'id' => $o->id,
            'time' => $o->created_at->format('H:i'),
            'items' => $o->sales->pluck('product.name')->implode(', '),
            'total' => (float) $o->total,
            'payment' => $o->payment_method,
        ])->take(20);

        return Inertia::render('Pos/Today', [
            'session' => $session ? [
                'id' => $session->id,
                'opened_at' => $session->opened_at->format('H:i'),
            ] : null,
            'summary' => $summary,
            'recentOrders' => $recent,
        ]);
    }
}
