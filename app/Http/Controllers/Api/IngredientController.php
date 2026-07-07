<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreIngredientRequest;
use App\Models\Ingredient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class IngredientController extends Controller
{
    public function index(): JsonResponse
    {
        $ingredients = Ingredient::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'item_type',
                'unit_type',
                'base_unit',
                'unit_price',
                'weighted_avg_price',
                'current_stock',
                'minimum_stock',
                'supplier_id',
            ])
            ->map(fn (Ingredient $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'item_type' => $i->item_type,
                'unit_type' => $i->unit_type,
                'base_unit' => $i->base_unit,
                'unit_price' => (float) $i->unit_price,
                'weighted_avg_price' => (float) ($i->weighted_avg_price ?? $i->unit_price),
                'current_stock' => (float) $i->current_stock,
                'minimum_stock' => (float) $i->minimum_stock,
                'stock_status' => $i->stock_status,
                'supplier_id' => $i->supplier_id,
            ]);

        return response()->json(['data' => $ingredients->values()->all()]);
    }

    public function store(StoreIngredientRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $ingredient = Ingredient::create([
            ...$request->validated(),
            'current_stock' => 0,
            'tenant_id' => $tenantId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bahan berhasil dibuat.',
            'data' => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'item_type' => $ingredient->item_type,
                'base_unit' => $ingredient->base_unit,
                'unit_price' => (float) $ingredient->unit_price,
                'current_stock' => 0,
            ],
        ], 201);
    }
}
