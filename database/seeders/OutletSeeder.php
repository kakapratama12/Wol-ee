<?php

namespace Database\Seeders;

use App\Models\Distribution;
use App\Models\DistributionItem;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletInventory;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class OutletSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'kafe-contoh')->first();

        if (! $tenant) {
            $this->command->info('Tenant not found. Run DatabaseSeeder first.');

            return;
        }

        // Create outlets
        $pusat = Outlet::updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Pusat / Dapur'],
            [
                'type' => null,
                'address' => 'Jl. Contoh No. 1, Jakarta',
                'is_active' => true,
            ],
        );

        $outletA = Outlet::updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Outlet Bandung'],
            [
                'type' => null,
                'address' => 'Jl. Asia Afrika No. 10, Bandung',
                'is_active' => true,
            ],
        );

        $outletB = Outlet::updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Outlet Surabaya'],
            [
                'type' => null,
                'address' => 'Jl. Pemuda No. 25, Surabaya',
                'is_active' => true,
            ],
        );

        $this->command->info('Outlets created: ' . $pusat->name . ', ' . $outletA->name . ', ' . $outletB->name);

        // Get ingredients and products
        $ingredients = Ingredient::where('tenant_id', $tenant->id)->get();
        $products = Product::where('tenant_id', $tenant->id)->get();

        // Distribute ingredients (bahan baku) - this is the main use case
        $this->seedIngredientDistribution($tenant, $pusat, $outletA, $ingredients, 'Tepung', 3000, 'g', 'Distribusi tepung ke Outlet Bandung');
        $this->seedIngredientDistribution($tenant, $pusat, $outletA, $ingredients, 'Susu', 5000, 'ml', 'Distribusi susu ke Outlet Bandung');
        $this->seedIngredientDistribution($tenant, $pusat, $outletB, $ingredients, 'Kopi', 2000, 'g', 'Distribusi kopi ke Outlet Surabaya');
        $this->seedIngredientDistribution($tenant, $pusat, $outletB, $ingredients, 'Gula', 3000, 'g', 'Distribusi gula ke Outlet Surabaya');

        // Also distribute a product (for demo)
        if ($products->isNotEmpty()) {
            $this->seedProductDistribution($tenant, $pusat, $outletA, $products, 'Matcha Latte', 50, 'Distribusi produk jadi ke Outlet Bandung');
        }

        $this->command->info('Sample distributions created.');
    }

    private function seedIngredientDistribution(
        Tenant $tenant,
        Outlet $from,
        Outlet $to,
        $ingredients,
        string $ingredientName,
        float $quantity,
        string $unit,
        string $notes,
    ): void {
        $ingredient = $ingredients->firstWhere('name', $ingredientName);

        if (! $ingredient) {
            return;
        }

        $distribution = Distribution::create([
            'tenant_id' => $tenant->id,
            'from_outlet_id' => $from->id,
            'to_outlet_id' => $to->id,
            'notes' => $notes,
            'distributed_at' => now()->subDays(3),
            'created_by' => $tenant->users()->first()?->id,
        ]);

        DistributionItem::create([
            'distribution_id' => $distribution->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => $quantity,
            'unit' => $unit,
        ]);

        // Update outlet ingredient inventory
        OutletInventory::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'outlet_id' => $to->id,
                'ingredient_id' => $ingredient->id,
                'product_id' => null,
            ],
            [
                'quantity' => $quantity,
                'unit' => $unit,
                'last_updated' => now(),
            ],
        );

        // Deduct from source ingredient stock
        $ingredient->decrement('current_stock', $quantity);
    }

    private function seedProductDistribution(
        Tenant $tenant,
        Outlet $from,
        Outlet $to,
        $products,
        string $productName,
        int $quantity,
        string $notes,
    ): void {
        $product = $products->firstWhere('name', $productName);

        if (! $product) {
            return;
        }

        $distribution = Distribution::create([
            'tenant_id' => $tenant->id,
            'from_outlet_id' => $from->id,
            'to_outlet_id' => $to->id,
            'notes' => $notes,
            'distributed_at' => now()->subDays(2),
            'created_by' => $tenant->users()->first()?->id,
        ]);

        DistributionItem::create([
            'distribution_id' => $distribution->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit' => $product->unit,
        ]);

        // Update outlet product inventory
        OutletInventory::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'outlet_id' => $to->id,
                'product_id' => $product->id,
                'ingredient_id' => null,
            ],
            [
                'quantity' => $quantity,
                'unit' => $product->unit,
                'last_updated' => now(),
            ],
        );
    }
}
