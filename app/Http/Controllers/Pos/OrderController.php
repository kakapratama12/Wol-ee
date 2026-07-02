<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StorePosOrderRequest;
use App\Models\PosOrder;
use App\Services\CashierSessionService;
use App\Services\PosOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function store(
        StorePosOrderRequest $request,
        CashierSessionService $sessions,
        PosOrderService $orders,
    ): JsonResponse|RedirectResponse {
        $session = $sessions->findOpenSession($request->user());

        if (! $session) {
            if ($request->header('X-Inertia')) {
                return redirect()->route('pos.session.open.form')
                    ->withErrors(['checkout' => 'Buka sesi kasir terlebih dahulu.']);
            }

            return response()->json(['message' => 'Buka sesi kasir terlebih dahulu.'], 422);
        }

        $validated = $request->validated();
        $lineItems = collect($validated['items'])
            ->map(fn (array $row) => [
                'product_id' => (int) $row['product_id'],
                'quantity' => (int) $row['quantity'],
            ])
            ->values()
            ->all();

        try {
            $order = $orders->checkout(
                session: $session,
                user: $request->user(),
                lineItems: $lineItems,
                paymentMethod: $validated['payment_method'],
                amountPaid: (float) $validated['amount_paid'],
                note: $validated['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['checkout' => $e->getMessage()]);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($request->header('X-Inertia')) {
            return redirect()->route('pos.orders.success', $order);
        }

        return response()->json([
            'message' => 'Transaksi berhasil.',
            'order' => [
                'id' => $order->id,
                'total' => (float) $order->total,
                'payment_method' => $order->payment_method,
                'change_amount' => (float) $order->change_amount,
                'sales' => $order->sales->map(fn ($sale) => [
                    'id' => $sale->id,
                    'product' => $sale->product?->name,
                    'quantity' => $sale->quantity,
                    'revenue' => (float) $sale->revenue,
                ]),
            ],
        ], 201);
    }

    public function void(
        Request $request,
        PosOrder $order,
        CashierSessionService $sessions,
        PosOrderService $orders,
    ): JsonResponse {
        $session = $sessions->findOpenSession($request->user());

        if (! $session) {
            return response()->json(['message' => 'Tidak ada sesi aktif.'], 422);
        }

        try {
            $orders->void($order, $session);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Transaksi di-void.']);
    }
}
