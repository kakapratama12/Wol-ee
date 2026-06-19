<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Ingredient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ingredient::query()->orderBy('name');

        if ($request->filled('ingredient')) {
            $search = mb_strtolower(trim((string) $request->input('ingredient')));
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
        }

        $items = $query->get()->map(fn (Ingredient $i) => [
            'id' => $i->id,
            'ingredient' => mb_strtolower($i->name),
            'current_stock' => (float) $i->current_stock,
            'minimum_stock' => (float) $i->minimum_stock,
            'unit' => $i->base_unit,
            'status' => $i->stock_status,
        ])->all();

        return ApiResponse::success('Daftar stok.', $items);
    }
}
