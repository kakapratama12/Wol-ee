<?php

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\Tenant;
use App\Services\BranchStockService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->branch = Branch::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Outlet A',
        'is_active' => true,
    ]);
    $this->service = app(BranchStockService::class);
});

it('membaca stok bahan dari saldo global saat ini', function () {
    $ingredient = Ingredient::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Susu',
        'item_type' => Ingredient::ITEM_RAW_MATERIAL,
        'unit_type' => 'weight',
        'base_unit' => 'ml',
        'unit_price' => 20,
        'current_stock' => 1500,
        'minimum_stock' => 100,
    ]);

    expect($this->service->getIngredientStock($ingredient->id, $this->branch->id))->toBe(1500.0);
    expect($this->service->getIngredientStock($ingredient->id, null))->toBe(1500.0);
});

it('mengembalikan nol untuk bahan yang tidak ada', function () {
    expect($this->service->getIngredientStock(99999, $this->branch->id))->toBe(0.0);
});

it('deduct per cabang belum diimplementasi', function () {
    expect(fn () => $this->service->deductForSale())->toThrow(BadMethodCallException::class);
});
