<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CloseCashierSessionRequest;
use App\Http\Requests\Pos\OpenCashierSessionRequest;
use App\Models\CashierSession;
use App\Models\OutletInventory;
use App\Models\PosOrder;
use App\Services\CashierSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SessionController extends Controller
{
    public function landing(CashierSessionService $sessions): Response
    {
        $user = auth()->user();
        $today = Carbon::today();

        $todaySession = CashierSession::query()
            ->where('user_id', $user->id)
            ->whereDate('opened_at', $today)
            ->withSum('posOrders', 'total')
            ->withCount('posOrders')
            ->first();

        $recentSessions = CashierSession::query()
            ->where('user_id', $user->id)
            ->where('opened_at', '>=', $today->copy()->subDays(5))
            ->whereNotNull('closed_at')
            ->withSum('posOrders', 'total')
            ->withCount('posOrders')
            ->orderByDesc('opened_at')
            ->get();


        // Outlet stock summary for landing page
        $outletId = $user->outlet_id;
        $stockSummary = [];
        if ($outletId) {
            $stockSummary = OutletInventory::query()
                ->with('ingredient:id,name,base_unit,minimum_stock')
                ->where('outlet_id', $outletId)
                ->whereHas('ingredient')
                ->orderBy('ingredient_id')
                ->get()
                ->map(fn ($oi) => [
                    'name' => $oi->ingredient?->name ?? '-',
                    'quantity' => (float) $oi->quantity,
                    'unit' => $oi->unit,
                    'minimum_stock' => $oi->ingredient ? (float) $oi->ingredient->minimum_stock : 0,
                    'status' => $oi->ingredient && $oi->ingredient->minimum_stock > 0
                        ? ($oi->quantity <= 0 ? 'habis' : ($oi->quantity <= $oi->ingredient->minimum_stock ? 'menipis' : 'aman'))
                        : 'aman',
                ])
                ->values()
                ->all();
        }
        return Inertia::render('Pos/Index', [
            'todaySession' => $todaySession ? [
                'id' => $todaySession->id,
                'status' => $todaySession->isOpen() ? 'open' : 'closed',
                'opened_at' => $todaySession->opened_at?->toIso8601String(),
                'closed_at' => $todaySession->closed_at?->toIso8601String(),
                'opening_cash' => (float) $todaySession->opening_cash,
                'total_omset' => (float) ($todaySession->pos_orders_sum_total ?? 0),
                'total_orders' => $todaySession->pos_orders_count ?? 0,
                'outlet' => $user->outlet?->name,
            ] : null,
            'recentSessions' => $recentSessions->map(fn ($s) => [
                'id' => $s->id,
                'date' => $s->opened_at?->format('d M Y'),
                'opened_at' => $s->opened_at?->format('H:i'),
                'closed_at' => $s->closed_at?->format('H:i'),
                'total_omset' => round(
                    (float) $s->total_cash + (float) $s->total_qris + (float) $s->total_transfer,
                    2,
                ),
                'total_orders' => $s->pos_orders_count ?? 0,
                'outlet' => $user->outlet?->name,
                'total_cash' => (float) $s->total_cash,
                'total_qris' => (float) $s->total_qris,
                'total_transfer' => (float) $s->total_transfer,
                'expected_cash' => round((float) $s->opening_cash + (float) $s->total_cash, 2),
                'variance' => (float) $s->variance,
            ]),
            'stockSummary' => $stockSummary,
        ]);
    }

    public function entry(CashierSessionService $sessions): Response|RedirectResponse
    {
        $session = $sessions->findOpenSession(auth()->user());

        if ($session) {
            return redirect()->route('pos.register');
        }

        return redirect()->route('pos.landing');
    }

    public function show(Request $request, CashierSessionService $sessions): JsonResponse
    {
        $session = $sessions->findOpenSession($request->user());

        return response()->json([
            'session' => $session ? [
                'id' => $session->id,
                'outlet_id' => $session->outlet_id,
                'opening_cash' => (float) $session->opening_cash,
                'total_cash' => (float) $session->total_cash,
                'total_qris' => (float) $session->total_qris,
                'total_transfer' => (float) $session->total_transfer,
                'opened_at' => $session->opened_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function openForm(CashierSessionService $sessions): Response|RedirectResponse
    {
        if ($sessions->findOpenSession(auth()->user())) {
            return redirect()->route('pos.register');
        }

        return Inertia::render('Pos/Session/Open', [
            'outlet' => auth()->user()->outlet?->name,
        ]);
    }

    public function open(OpenCashierSessionRequest $request, CashierSessionService $sessions): JsonResponse|RedirectResponse
    {
        try {
            $session = $sessions->open(
                $request->user(),
                (float) $request->validated('opening_cash'),
            );
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['opening_cash' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Sesi kasir dibuka.',
                'session' => [
                    'id' => $session->id,
                    'opening_availability_snapshot' => $session->opening_availability_snapshot,
                ],
            ], 201);
        }

        return redirect()->route('pos.session.summary.page');
    }

    public function summaryPage(CashierSessionService $sessions): Response|RedirectResponse
    {
        $session = $sessions->findOpenSession(auth()->user());

        if (! $session) {
            return redirect()->route('pos.session.open.form');
        }

        $snapshot = collect($session->opening_availability_snapshot ?? []);
        $attention = $snapshot->whereIn('bucket', ['low', 'out'])->values();
        $ready = $snapshot->where('bucket', 'ready')->values();

        return Inertia::render('Pos/Session/Summary', [
            'ready' => $ready,
            'attention' => $attention,
            'outlet' => auth()->user()->outlet?->name,
        ]);
    }

    public function skipSummary(): RedirectResponse
    {
        return redirect()->route('pos.register');
    }

    public function summary(Request $request, CashierSessionService $sessions): JsonResponse
    {
        $session = $sessions->findOpenSession($request->user());

        if (! $session) {
            return response()->json(['message' => 'Tidak ada sesi aktif.'], 404);
        }

        return response()->json([
            'snapshot' => $session->opening_availability_snapshot ?? [],
        ]);
    }

    public function closeForm(CashierSessionService $sessions): Response|RedirectResponse
    {
        $session = $sessions->findOpenSession(auth()->user());

        if (! $session) {
            return redirect()->route('pos.session.open.form');
        }

        $session->load('outlet');

        return Inertia::render('Pos/Session/Close', [
            'session' => [
                'opening_cash' => (float) $session->opening_cash,
                'total_cash' => (float) $session->total_cash,
                'total_qris' => (float) $session->total_qris,
                'total_transfer' => (float) $session->total_transfer,
                'expected_cash' => round((float) $session->opening_cash + (float) $session->total_cash, 2),
                'outlet' => $session->outlet?->name,
            ],
            'salesSummary' => $sessions->salesSummary($session),
            'orderCount' => $session->posOrders()->where('status', PosOrder::STATUS_COMPLETED)->count(),
        ]);
    }

    public function close(CloseCashierSessionRequest $request, CashierSessionService $sessions): JsonResponse|RedirectResponse
    {
        $session = $sessions->findOpenSession($request->user());

        if (! $session) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Tidak ada sesi aktif.'], 404);
            }

            return redirect()->route('pos.session.open.form');
        }

        try {
            $summary = $sessions->close(
                $session,
                (float) $request->validated('actual_cash'),
                $request->validated('closing_note')
            );
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['actual_cash' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Sesi kasir ditutup.',
                'summary' => $summary,
            ]);
        }

        return redirect()->route('pos.landing')->with('success', 'Sesi kasir ditutup.');
    }
}
