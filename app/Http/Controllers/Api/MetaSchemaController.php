<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Partner;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class MetaSchemaController extends Controller
{
    /**
     * Return entity schemas for bot integration.
     *
     * This endpoint is the single source of truth for field definitions.
     * Bot fetches this on startup to stay in sync with Laravel.
     *
     * GET /api/v1/meta/schema
     */
    public function schema(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'version' => '1.0.0',
            'updated_at' => now()->toIso8601String(),
            'data' => [
                'ingredient' => [
                    'display' => 'Bahan Baku',
                    'fields' => [
                        'name' => ['required' => true, 'type' => 'string', 'max' => 255, 'unique' => true],
                        'item_type' => ['required' => false, 'type' => 'string', 'default' => Ingredient::ITEM_RAW_MATERIAL, 'options' => [Ingredient::ITEM_RAW_MATERIAL, Ingredient::ITEM_PREP, Ingredient::ITEM_FINISHED_GOODS]],
                        'unit_type' => ['required' => true, 'type' => 'string', 'options' => ['weight', 'volume', 'count']],
                        'base_unit' => ['required' => true, 'type' => 'string', 'max' => 50],
                        'unit_price' => ['required' => true, 'type' => 'number', 'min' => 0],
                        'minimum_stock' => ['required' => false, 'type' => 'number', 'default' => 0, 'min' => 0],
                        'supplier_id' => ['required' => false, 'type' => 'integer', 'nullable' => true],
                    ],
                ],
                'product' => [
                    'display' => 'Produk',
                    'fields' => [
                        'name' => ['required' => true, 'type' => 'string', 'max' => 255, 'unique' => true],
                        'unit' => ['required' => true, 'type' => 'string', 'max' => 50],
                        'selling_price' => ['required' => true, 'type' => 'number', 'min' => 0],
                        'recipe_type' => ['required' => false, 'type' => 'string', 'default' => Product::RECIPE_UNIT, 'options' => [Product::RECIPE_UNIT, Product::RECIPE_BATCH]],
                        'estimated_yield_per_batch' => ['required' => false, 'type' => 'integer', 'default' => 1, 'min' => 1],
                        'is_prep' => ['required' => false, 'type' => 'boolean', 'default' => false],
                    ],
                ],
                'recipe' => [
                    'display' => 'Resep',
                    'fields' => [
                        'product_id' => ['required' => true, 'type' => 'integer', 'exists' => 'products'],
                        'items' => ['required' => true, 'type' => 'array', 'min_items' => 1],
                    ],
                    'item_fields' => [
                        'ingredient_id' => ['required' => true, 'type' => 'integer', 'exists' => 'ingredients'],
                        'quantity' => ['required' => true, 'type' => 'number', 'min' => 0.0001],
                    ],
                ],
                'transaction' => [
                    'display' => 'Pembelian',
                    'fields' => [
                        'ingredient' => ['required' => false, 'type' => 'string', 'desc' => 'Nama bahan (fuzzy match)'],
                        'ingredient_id' => ['required' => false, 'type' => 'integer', 'exists' => 'ingredients'],
                        'quantity' => ['required' => true, 'type' => 'number', 'min' => 0.01],
                        'unit_price' => ['required' => false, 'type' => 'number', 'min' => 0, 'desc' => 'Harga per satuan'],
                        'total' => ['required' => false, 'type' => 'number', 'min' => 0, 'desc' => 'Total harga'],
                        'note' => ['required' => false, 'type' => 'string', 'max' => 255],
                        'occurred_at' => ['required' => false, 'type' => 'date'],
                    ],
                    'validation_notes' => 'unit_price ATAU total wajib diisi. ingredient ATAU ingredient_id wajib diisi.',
                ],
                'sale' => [
                    'display' => 'Penjualan',
                    'fields' => [
                        'product' => ['required' => false, 'type' => 'string', 'desc' => 'Nama produk (fuzzy match)'],
                        'product_id' => ['required' => false, 'type' => 'integer', 'exists' => 'products'],
                        'quantity' => ['required' => true, 'type' => 'integer', 'min' => 1],
                        'unit_price' => ['required' => false, 'type' => 'number', 'min' => 0],
                        'total' => ['required' => false, 'type' => 'number', 'min' => 0, 'desc' => 'Total revenue'],
                        'outlet_id' => ['required' => false, 'type' => 'integer', 'nullable' => true],
                        'note' => ['required' => false, 'type' => 'string', 'max' => 255],
                        'occurred_at' => ['required' => false, 'type' => 'date'],
                    ],
                    'computed_fields' => ['revenue', 'cogs', 'profit', 'margin'],
                ],
                'invoice' => [
                    'display' => 'Invoice',
                    'fields' => [
                        'partner_id' => ['required' => false, 'type' => 'integer', 'exists' => 'partners'],
                        'partner' => ['required' => false, 'type' => 'string', 'desc' => 'Nama partner (fuzzy match)'],
                        'po_number' => ['required' => false, 'type' => 'string', 'max' => 255],
                        'amount' => ['required' => true, 'type' => 'number', 'min' => 0],
                        'due_date' => ['required' => false, 'type' => 'date'],
                        'note' => ['required' => false, 'type' => 'string', 'max' => 255],
                        'items' => ['required' => false, 'type' => 'array', 'desc' => 'Line items (opsional, amount dipakai jika kosong)'],
                    ],
                    'item_fields' => [
                        'description' => ['required' => true, 'type' => 'string'],
                        'quantity' => ['required' => true, 'type' => 'number', 'min' => 0.0001],
                        'unit_price' => ['required' => true, 'type' => 'number', 'min' => 0],
                    ],
                ],
                'partner' => [
                    'display' => 'Mitra',
                    'fields' => [
                        'name' => ['required' => true, 'type' => 'string', 'max' => 255, 'unique' => true],
                        'type' => ['required' => true, 'type' => 'string', 'options' => [Partner::TYPE_CUSTOMER, Partner::TYPE_SUPPLIER]],
                        'contact' => ['required' => false, 'type' => 'string', 'max' => 255],
                        'phone' => ['required' => false, 'type' => 'string', 'max' => 50],
                        'email' => ['required' => false, 'type' => 'email', 'max' => 255],
                        'address' => ['required' => false, 'type' => 'string', 'max' => 1000],
                    ],
                ],
                'expense' => [
                    'display' => 'Pengeluaran',
                    'fields' => [
                        'description' => ['required' => true, 'type' => 'string', 'max' => 255],
                        'amount' => ['required' => true, 'type' => 'number', 'min' => 0],
                        'category' => ['required' => false, 'type' => 'string', 'options' => ['bahan_baku', 'operasional', 'logistik', 'overhead', 'non_operasional']],
                        'occurred_at' => ['required' => false, 'type' => 'date'],
                        'outlet_id' => ['required' => false, 'type' => 'integer', 'nullable' => true],
                    ],
                ],
                'production' => [
                    'display' => 'Produksi',
                    'fields' => [
                        'product_id' => ['required' => true, 'type' => 'integer', 'exists' => 'products'],
                        'quantity' => ['required' => true, 'type' => 'integer', 'min' => 1],
                        'notes' => ['required' => false, 'type' => 'string', 'max' => 255],
                    ],
                ],
            ],
        ]);
    }
}
