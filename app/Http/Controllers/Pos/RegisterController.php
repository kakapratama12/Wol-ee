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

        $session->load('branch');

        $products = Product::query()
            ->where('is_active', true)
            ->where('is_prep', false)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($availability) {
                $max = $availability->estimateMaxPortions($product);

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
                'branch' => $session->branch?->name,
                'opened_at' => $session->opened_at?->toIso8601String(),
            ],
            'products' => $products,
        ]);
    }

    public function success(PosOrder $order): Response
    {
        $order->load(['sales.product', 'branch']);

        return Inertia::render('Pos/Orders/Success', [
            'order' => [
                'id' => $order->id,
                'total' => (float) $order->total,
                'payment_method' => $order->payment_method,
                'amount_paid' => (float) $order->amount_paid,
                'change_amount' => (float) $order->change_amount,
                'branch' => $order->branch?->name,
                'created_at' => $order->created_at?->toIso8601String(),
                'sales' => $order->sales->map(fn ($sale) => [
                    'product' => $sale->product?->name,
                    'quantity' => $sale->quantity,
                    'revenue' => (float) $sale->revenue,
                ]),
            ],
        ]);
    }
}
