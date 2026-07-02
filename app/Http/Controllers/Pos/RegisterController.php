<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Models\Product;
use App\Services\CashierSessionService;
use App\Services\ProductAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function index(
        CashierSessionService $sessions,
        ProductAvailabilityService $availability,
    ): Response|RedirectResponse {
        $user = auth()->user();
        $session = $sessions->findOpenSession($user);

        if (! $session) {
            return redirect()->route('pos.session.open.form');
        }

        $session->load('outlet');

        $products = Product::query()
            ->where('is_active', true)
            ->where('is_prep', false)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($availability, $session) {
                $max = $availability->estimateMaxPortions($product, $session->outlet_id);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'selling_price' => (float) $product->selling_price,
                    'recipe_type' => $product->recipe_type,
                    'max_portions' => $max,
                    'disabled' => $product->isBatch() && $max <= 0,
                ];
            });

        return Inertia::render('Pos/Register', [
            'session' => [
                'id' => $session->id,
                'outlet' => $session->outlet?->name,
                'opened_at' => $session->opened_at?->toIso8601String(),
            ],
            'products' => $products,
        ]);
    }

    public function success(PosOrder $order): Response
    {
        $this->authorizePosOrder($order);

        $order->load(['sales.product', 'outlet']);

        return Inertia::render('Pos/Orders/Success', [
            'order' => [
                'id' => $order->id,
                'total' => (float) $order->total,
                'payment_method' => $order->payment_method,
                'amount_paid' => (float) $order->amount_paid,
                'change_amount' => (float) $order->change_amount,
                'outlet' => $order->outlet?->name,
                'created_at' => $order->created_at?->toIso8601String(),
                'sales' => $order->sales->map(fn ($sale) => [
                    'product' => $sale->product?->name,
                    'quantity' => $sale->quantity,
                    'revenue' => (float) $sale->revenue,
                ]),
            ],
        ]);
    }

    public function receipt(PosOrder $order): Response
    {
        $this->authorizePosOrder($order);

        $order->load(['sales.product', 'outlet', 'user']);

        return Inertia::render('Pos/Orders/Receipt', [
            'order' => [
                'id' => $order->id,
                'total' => (float) $order->total,
                'payment_method' => $order->payment_method,
                'amount_paid' => (float) $order->amount_paid,
                'change_amount' => (float) $order->change_amount,
                'outlet' => $order->outlet?->name,
                'cashier' => $order->user?->name,
                'created_at' => $order->created_at?->toIso8601String(),
                'sales' => $order->sales->map(fn ($sale) => [
                    'product' => $sale->product?->name,
                    'quantity' => $sale->quantity,
                    'unit_price' => (float) $sale->unit_price,
                    'revenue' => (float) $sale->revenue,
                ]),
            ],
        ]);
    }

    private function authorizePosOrder(PosOrder $order): void
    {
        $user = auth()->user();

        abort_unless($user && $order->tenant_id === $user->tenant_id, 403);
        abort_unless($user->isStaff(), 403);
    }
}
