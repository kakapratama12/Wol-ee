<?php

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Services\CogsService;
use App\Services\InventoryService;
use App\Services\SaleService;

function setupProduct(): Product
{
    $tepung = Ingredient::create([
        'name' => 'Tepung',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 20,       // Rp 20/g
        'current_stock' => 5000,  // 5kg
        'minimum_stock' => 1000,
    ]);

    $product = Product::create([
        'name' => 'Roti Goreng',
        'unit' => 'pcs',
        'selling_price' => 5000,
    ]);

    RecipeItem::create([
        'product_id' => $product->id,
        'ingredient_id' => $tepung->id,
        'quantity' => 100, // 100g per pcs
    ]);

    return $product;
}

function saleService(): SaleService
{
    return new SaleService(new CogsService(), new InventoryService());
}

it('mencatat penjualan dengan snapshot COGS dan profit', function () {
    $product = setupProduct();

    $sale = saleService()->record($product, quantity: 10);

    // COGS/unit = 100g * 20 = 2000 ; total = 20000 ; revenue = 50000
    expect((float) $sale->revenue)->toBe(50000.0)
        ->and((float) $sale->cogs)->toBe(20000.0)
        ->and((float) $sale->profit)->toBe(30000.0)
        ->and((float) $sale->margin)->toBe(60.0);
});

it('mengurangi stok bahan sesuai resep saat penjualan', function () {
    $product = setupProduct();

    saleService()->record($product, quantity: 10);

    // 5000g - (100g * 10) = 4000g
    $tepung = Ingredient::where('name', 'Tepung')->first();
    expect((float) $tepung->current_stock)->toBe(4000.0);
});

it('mencatat stock movement bertipe usage', function () {
    $product = setupProduct();

    $sale = saleService()->record($product, quantity: 3);

    $this->assertDatabaseHas('stock_movements', [
        'type' => 'usage',
        'source_type' => \App\Models\Sale::class,
        'source_id' => $sale->id,
    ]);
});
