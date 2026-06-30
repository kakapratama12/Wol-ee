<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBatchTransactionRequest;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Http\Support\ApiResponse;
use App\Models\Ingredient;
use App\Models\Transaction;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 10), 50);

        $query = Transaction::query()
            ->with('ingredient:id,name,base_unit')
            ->latest('occurred_at');

        if ($request->filled('date')) {
            $query->whereDate('occurred_at', $request->date('date'));
        }

        $transactions = $query
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'ingredient' => $transaction->ingredient?->name,
                'base_unit' => $transaction->ingredient?->base_unit,
                'quantity' => (float) $transaction->quantity,
                'unit_price' => (float) $transaction->unit_price,
                'total' => (float) $transaction->total,
                'source' => $transaction->source,
                'note' => $transaction->note,
                'occurred_at' => $transaction->occurred_at?->toIso8601String(),
            ]);

        return ApiResponse::success('Riwayat pembelian.', $transactions->values()->all());
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        // Idempotency check
        if ($idempotencyKey = $request->header('X-Idempotency-Key')) {
            $existing = Transaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return ApiResponse::success('Pembelian sudah tercatat.', [
                    'id' => $existing->id,
                    'ingredient' => $existing->ingredient?->name,
                    'quantity' => (float) $existing->quantity,
                    'total' => (float) $existing->total,
                    'idempotent_replay' => true,
                ]);
            }
        }

        $searchName = trim((string) $request->input('ingredient', ''));
        $ingredient = $this->resolveIngredient($request);

        if (! $ingredient) {
            return ApiResponse::error(
                message: "Bahan '{$searchName}' tidak ditemukan.",
                errorCode: 'INGREDIENT_NOT_FOUND',
                status: 422,
                extra: $this->ingredientNotFoundExtra(),
            );
        }

        $quantity = (float) $request->float('quantity');
        $unitPrice = $request->filled('unit_price')
            ? (float) $request->float('unit_price')
            : round((float) $request->float('total') / max($quantity, 1e-9), 4);

        $transaction = $this->inventory->recordPurchase(
            ingredient: $ingredient,
            quantity: $quantity,
            unitPrice: $unitPrice,
            source: 'bot',
            userId: $request->user()?->id,
            note: $request->input('note'),
            occurredAt: $request->date('occurred_at'),
            idempotencyKey: $idempotencyKey,
        );

        $ingredient->refresh();

        return ApiResponse::success('Pembelian tercatat.', [
            'id' => $transaction->id,
            'ingredient' => mb_strtolower($ingredient->name),
            'quantity' => (float) $transaction->quantity,
            'unit_price' => (float) $transaction->unit_price,
            'total' => (float) $transaction->total,
            'new_stock' => (float) $ingredient->current_stock,
            'stock_status' => $ingredient->stock_status,
        ], 201);
    }

    public function storeBatch(StoreBatchTransactionRequest $request): JsonResponse
    {
        $items = $request->input('items', []);
        $resolved = [];
        $errors = [];

        foreach ($items as $index => $item) {
            $searchName = trim((string) ($item['ingredient'] ?? ''));
            $ingredient = $this->resolveIngredientByInput($item);

            if (! $ingredient) {
                $errors[] = [
                    'index' => $index,
                    'ingredient' => $searchName,
                    'error_code' => 'INGREDIENT_NOT_FOUND',
                    'message' => "Bahan '{$searchName}' tidak ditemukan.",
                ];

                continue;
            }

            $quantity = (float) $item['quantity'];
            $unitPrice = ! empty($item['unit_price'])
                ? (float) $item['unit_price']
                : round((float) $item['total'] / max($quantity, 1e-9), 4);

            $resolved[] = [
                'ingredient' => $ingredient,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        if ($errors !== []) {
            return ApiResponse::error(
                message: 'Beberapa bahan tidak ditemukan.',
                errorCode: 'BATCH_VALIDATION_FAILED',
                status: 422,
                extra: array_merge(['errors' => $errors], $this->ingredientNotFoundExtra()),
            );
        }

        $note = $request->input('note');
        $occurredAt = $request->date('occurred_at');
        $userId = $request->user()?->id;

        $results = DB::transaction(function () use ($resolved, $note, $occurredAt, $userId) {
            $transactions = [];
            $totalAmount = 0.0;

            foreach ($resolved as $row) {
                $ingredient = $row['ingredient'];
                $transaction = $this->inventory->recordPurchase(
                    ingredient: $ingredient,
                    quantity: $row['quantity'],
                    unitPrice: $row['unit_price'],
                    source: 'bot',
                    userId: $userId,
                    note: $note,
                    occurredAt: $occurredAt,
                );

                $ingredient->refresh();
                $totalAmount += (float) $transaction->total;

                $transactions[] = [
                    'id' => $transaction->id,
                    'ingredient' => $ingredient->name,
                    'quantity' => (float) $transaction->quantity,
                    'total' => (float) $transaction->total,
                    'new_stock' => (float) $ingredient->current_stock,
                ];
            }

            return [
                'transactions' => $transactions,
                'total_amount' => round($totalAmount, 2),
            ];
        });

        return ApiResponse::success('Batch pembelian tercatat.', $results, 201);
    }

    private function resolveIngredient(StoreTransactionRequest $request): ?Ingredient
    {
        if ($request->filled('ingredient_id')) {
            return Ingredient::find($request->integer('ingredient_id'));
        }

        $name = trim((string) $request->input('ingredient'));

        return Ingredient::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveIngredientByInput(array $item): ?Ingredient
    {
        if (! empty($item['ingredient_id'])) {
            return Ingredient::find((int) $item['ingredient_id']);
        }

        $name = trim((string) ($item['ingredient'] ?? ''));

        return Ingredient::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }

    /**
     * @return array{available_items: list<string>, dashboard_url: string}
     */
    private function ingredientNotFoundExtra(): array
    {
        return [
            'available_items' => Ingredient::query()
                ->orderBy('name')
                ->limit(50)
                ->pluck('name')
                ->all(),
            'dashboard_url' => rtrim(config('app.url'), '/').'/inventory',
        ];
    }
}
