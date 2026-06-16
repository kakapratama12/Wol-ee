<?php

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Services\CogsService;

function makeMatchaLatte(): Product
{
    // Harga disimpan per base_unit.
    $susu = Ingredient::create([
        'name' => 'Susu',
        'unit_type' => 'gramasi',
        'base_unit' => 'ml',
        'unit_price' => 18,      // Rp 18.000/L => 18/ml
        'current_stock' => 5000,
        'minimum_stock' => 1000,
    ]);
    $matcha = Ingredient::create([
        'name' => 'Pasta Matcha',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 250,     // Rp 250.000/kg => 250/g
        'current_stock' => 1000,
        'minimum_stock' => 200,
    ]);
    $gula = Ingredient::create([
        'name' => 'Gula',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 15,      // Rp 15.000/kg => 15/g
        'current_stock' => 5000,
        'minimum_stock' => 500,
    ]);

    $product = Product::create([
        'name' => 'Matcha Latte',
        'unit' => 'cup',
        'selling_price' => 45000,
    ]);

    RecipeItem::create(['product_id' => $product->id, 'ingredient_id' => $susu->id, 'quantity' => 200]);
    RecipeItem::create(['product_id' => $product->id, 'ingredient_id' => $matcha->id, 'quantity' => 20]);
    RecipeItem::create(['product_id' => $product->id, 'ingredient_id' => $gula->id, 'quantity' => 15]);

    return $product;
}

it('menghitung COGS per porsi dari resep', function () {
    $product = makeMatchaLatte();

    // 200*18 + 20*250 + 15*15 = 3600 + 5000 + 225 = 8825
    expect((new CogsService())->cogsForProduct($product))->toBe(8825.0);
});

it('menghitung margin berdasarkan harga jual', function () {
    $product = makeMatchaLatte();

    // (45000 - 8825) / 45000 * 100 = 80.39%
    expect((new CogsService())->margin($product))->toBe(80.39);
});

it('menambahkan waste ke COGS', function () {
    $cogs = new CogsService();

    expect($cogs->withWaste(8825, 15))->toBe(10148.75);
});

it('memberi rincian COGS per bahan', function () {
    $product = makeMatchaLatte();

    $breakdown = (new CogsService())->breakdown($product);

    expect($breakdown)->toHaveCount(3)
        ->and(collect($breakdown)->firstWhere('ingredient', 'Pasta Matcha')['cost'])->toBe(5000.0);
});
