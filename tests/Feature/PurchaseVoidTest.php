<?php

use App\Models\Ingredient;
use App\Models\Transaction;
use App\Services\InventoryService;
use App\Services\PurchaseService;

beforeEach(function () {
    authenticateTestTenant();
});

it('menghapus pembelian dan menyesuaikan weighted average', function () {
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
    $tx1 = $inventory->recordPurchase($tepung, quantity: 10, unitPrice: 10000);
    $tx2 = $inventory->recordPurchase($tepung->fresh(), quantity: 5, unitPrice: 12000);

    $tepung->refresh();
    expect((float) $tepung->current_stock)->toBe(15.0)
        ->and((float) $tepung->weighted_avg_price)->toBe(10666.6667);

    (new PurchaseService($inventory))->void($tx2);

    $tepung->refresh();
    expect((float) $tepung->current_stock)->toBe(10.0)
        ->and((float) $tepung->weighted_avg_price)->toBe(10000.0);

    expect(Transaction::find($tx2->id))->toBeNull();
});

it('menolak hapus pembelian jika stok sudah terpakai', function () {
    $tepung = Ingredient::create([
        'name' => 'Gula',
        'unit_type' => 'weight',
        'base_unit' => 'kg',
        'unit_price' => 8000,
        'weighted_avg_price' => 8000,
        'current_stock' => 0,
        'minimum_stock' => 100,
    ]);

    $inventory = new InventoryService();
    $tx = $inventory->recordPurchase($tepung, quantity: 10, unitPrice: 8000);

    $tepung->update(['current_stock' => 3]);

    expect(fn () => (new PurchaseService($inventory))->void($tx))
        ->toThrow(InvalidArgumentException::class);
});

it('memperbarui pembelian dengan bahan dan jumlah baru', function () {
    $tepung = Ingredient::create([
        'name' => 'Tepung',
        'unit_type' => 'weight',
        'base_unit' => 'kg',
        'unit_price' => 10000,
        'weighted_avg_price' => 10000,
        'current_stock' => 0,
        'minimum_stock' => 1000,
    ]);

    $gula = Ingredient::create([
        'name' => 'Gula',
        'unit_type' => 'weight',
        'base_unit' => 'kg',
        'unit_price' => 5000,
        'weighted_avg_price' => 5000,
        'current_stock' => 0,
        'minimum_stock' => 100,
    ]);

    $inventory = new InventoryService();
    $tx = $inventory->recordPurchase($tepung, quantity: 10, unitPrice: 10000);

    $updated = (new PurchaseService($inventory))->update(
        transaction: $tx,
        ingredient: $gula,
        quantity: 4,
        unitPrice: 6000,
    );

    $tepung->refresh();
    $gula->refresh();

    expect((float) $tepung->current_stock)->toBe(0.0)
        ->and((float) $gula->current_stock)->toBe(4.0)
        ->and((float) $gula->weighted_avg_price)->toBe(6000.0)
        ->and($updated->ingredient_id)->toBe($gula->id);
});
