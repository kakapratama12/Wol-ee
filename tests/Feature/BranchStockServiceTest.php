<?php

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletInventory;
use App\Models\Tenant;
use App\Services\BranchStockService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->branch = Outlet::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Outlet A',
        'type' => 'primary',
        'is_active' => true,
    ]);
    $this->service = app(BranchStockService::class);
});

it('membaca stok bahan dari outlet_inventory dan global', function () {
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

    // Branch stock reads from outlet_inventory
    OutletInventory::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->branch->id,
        'ingredient_id' => $ingredient->id,
        'product_id' => null,
        'quantity' => 500,
        'unit' => 'ml',
    ]);

    expect($this->service->getIngredientStock($ingredient->id, $this->branch->id))->toBe(500.0);
    // Global stock reads from ingredients.current_stock
    expect($this->service->getIngredientStock($ingredient->id, null))->toBe(1500.0);
});

it('mengembalikan nol untuk bahan yang tidak ada', function () {
    expect($this->service->getIngredientStock(99999, $this->branch->id))->toBe(0.0);
});

it('deductForSale membutuhkan argumen yang benar', function () {
    expect(fn () => $this->service->deductForSale())->toThrow(ArgumentCountError::class);
});
