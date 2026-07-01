<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CloseCashierSessionRequest;
use App\Http\Requests\Pos\OpenCashierSessionRequest;
use App\Services\CashierSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SessionController extends Controller
{
    public function entry(CashierSessionService $sessions): RedirectResponse
    {
        $session = $sessions->findOpenSession(auth()->user());

        return $session
            ? redirect()->route('pos.register')
            : redirect()->route('pos.session.open.form');
    }

    public function show(Request $request, CashierSessionService $sessions): JsonResponse
    {
        $session = $sessions->findOpenSession($request->user());

        return response()->json([
            'session' => $session ? [
                'id' => $session->id,
                'branch_id' => $session->branch_id,
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
            'branch' => auth()->user()->branch?->name,
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

        $session->load('branch');

        return Inertia::render('Pos/Session/Close', [
            'session' => [
                'opening_cash' => (float) $session->opening_cash,
                'total_cash' => (float) $session->total_cash,
                'total_qris' => (float) $session->total_qris,
                'total_transfer' => (float) $session->total_transfer,
                'expected_cash' => round((float) $session->opening_cash + (float) $session->total_cash, 2),
                'branch' => $session->branch?->name,
            ],
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
            $summary = $sessions->close($session, (float) $request->validated('actual_cash'));
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

        return redirect()->route('pos.session.open.form')->with('success', 'Sesi kasir ditutup.');
    }
}
