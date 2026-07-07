<?php

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\Sale;
use App\Services\CogsService;
use App\Services\InventoryService;
use App\Services\BranchStockService;
use App\Services\SaleService;

beforeEach(function () {
    authenticateTestTenant();
});

it('menghitung weighted average setelah beberapa pembelian', function () {
    $tepung = Ingredient::create([
        'name' => 'Tepung',
        'unit_type' => 'weight',
        'base_unit' => 'kg',
        'unit_price' => 10000,
        'weighted_avg_price' => 10000,
        'current_stock' => 0,
        'minimum_stock' => 1000,
    ]);

    $inventory = new InventoryService();

    // Beli 10kg @ Rp 10.000/kg
    $inventory->recordPurchase($tepung, quantity: 10, unitPrice: 10000);
    $tepung->refresh();
    expect((float) $tepung->weighted_avg_price)->toBe(10000.0);

    // Beli 5kg @ Rp 12.000/kg → avg = (10*10000 + 5*12000) / 15 = 10666.6667
    $inventory->recordPurchase($tepung->fresh(), quantity: 5, unitPrice: 12000);
    $tepung->refresh();
    expect((float) $tepung->weighted_avg_price)->toBe(10666.6667);

    // Beli 3kg @ Rp 15.000/kg → avg = (15*10666.6667 + 3*15000) / 18 = 11388.8889
    $inventory->recordPurchase($tepung->fresh(), quantity: 3, unitPrice: 15000);
    $tepung->refresh();
    expect((float) $tepung->weighted_avg_price)->toBe(11388.8889);
});

it('snapshot COGS penjualan tidak berubah setelah pembelian baru', function () {
    $tepung = Ingredient::create([
        'name' => 'Tepung WA',
        'unit_type' => 'weight',
        'base_unit' => 'kg',
        'unit_price' => 10000,
        'weighted_avg_price' => 10000,
        'current_stock' => 10,
        'minimum_stock' => 1,
    ]);

    $product = Product::create([
        'name' => 'Roti Test',
        'unit' => 'pcs',
        'selling_price' => 50000,
    ]);

    RecipeItem::create([
        'product_id' => $product->id,
        'ingredient_id' => $tepung->id,
        'quantity' => 1, // 1kg per pcs
    ]);

    $inventory = new InventoryService();
    $inventory->recordPurchase($tepung, quantity: 5, unitPrice: 12000);
    $tepung->refresh();

    // weighted avg = (10*10000 + 5*12000) / 15 = 10666.6667
    $expectedCogsPerUnit = round(10666.6667 * 1, 2);

    $saleService = new SaleService(new CogsService(), $inventory, new BranchStockService());
    $sale = $saleService->record($product, quantity: 1);

    expect((float) $sale->cogs)->toBe($expectedCogsPerUnit);

    // Pembelian baru mengubah weighted avg
    $inventory->recordPurchase($tepung->fresh(), quantity: 3, unitPrice: 15000);
    $tepung->refresh();
    expect((float) $tepung->weighted_avg_price)->not->toBe(10666.6667);

    // Snapshot penjualan lama tetap
    expect((float) Sale::find($sale->id)->cogs)->toBe($expectedCogsPerUnit);
});

it('COGS produk memakai weighted average bukan harga beli terakhir', function () {
    $tepung = Ingredient::create([
        'name' => 'Tepung COGS',
        'unit_type' => 'weight',
        'base_unit' => 'g',
        'unit_price' => 12,
        'weighted_avg_price' => 10,
        'current_stock' => 1000,
        'minimum_stock' => 100,
    ]);

    $product = Product::create([
        'name' => 'Kue',
        'unit' => 'pcs',
        'selling_price' => 10000,
    ]);

    RecipeItem::create([
        'product_id' => $product->id,
        'ingredient_id' => $tepung->id,
        'quantity' => 100,
    ]);

    // 100g * 10 (weighted) = 1000, bukan 100g * 12 (last price) = 1200
    expect((new CogsService())->cogsForProduct($product))->toBe(1000.0);
});
