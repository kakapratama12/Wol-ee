<?php

use App\Events\SaleRecorded;
use App\Listeners\SendLowStockAlert;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\Sale;
use App\Services\CogsService;
use App\Services\InventoryService;
use App\Services\SaleService;
use App\Support\TelegramNotifier;
use Illuminate\Support\Facades\Event;

function makeProductWithStock(float $stock, float $minimum, float $usagePerUnit = 100): Product
{
    authenticateTestTenant();
    $ingredient = Ingredient::create([
        'name' => 'Tepung',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 20,
        'current_stock' => $stock,
        'minimum_stock' => $minimum,
    ]);

    $product = Product::create([
        'name' => 'Roti Goreng',
        'unit' => 'pcs',
        'selling_price' => 5000,
    ]);

    RecipeItem::create([
        'product_id' => $product->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => $usagePerUnit,
    ]);

    return $product;
}

it('memancarkan event SaleRecorded saat penjualan dicatat', function () {
    Event::fake([SaleRecorded::class]);

    $product = makeProductWithStock(stock: 5000, minimum: 1000);
    $sale = (new SaleService(new CogsService(), new InventoryService()))
        ->record($product, quantity: 1);

    Event::assertDispatched(SaleRecorded::class, fn (SaleRecorded $e) => $e->sale->is($sale));
});

it('mengirim peringatan saat stok bahan menipis setelah penjualan', function () {
    // Stok 1500g, min 1000g, pakai 600g => sisa 900g (<= min => menipis)
    $product = makeProductWithStock(stock: 1500, minimum: 1000, usagePerUnit: 600);
    $sale = (new SaleService(new CogsService(), new InventoryService()))
        ->record($product, quantity: 1);

    $notifier = Mockery::mock(TelegramNotifier::class);
    $notifier->shouldReceive('send')->once();

    (new SendLowStockAlert($notifier))->handle(new SaleRecorded($sale));
});

it('tidak mengirim peringatan saat stok masih aman', function () {
    // Stok 5000g, min 1000g, pakai 100g => sisa 4900g (aman)
    $product = makeProductWithStock(stock: 5000, minimum: 1000, usagePerUnit: 100);
    $sale = (new SaleService(new CogsService(), new InventoryService()))
        ->record($product, quantity: 1);

    $notifier = Mockery::mock(TelegramNotifier::class);
    $notifier->shouldReceive('send')->never();

    (new SendLowStockAlert($notifier))->handle(new SaleRecorded($sale));
});
