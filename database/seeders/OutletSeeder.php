<?php

namespace Database\Seeders;

use App\Models\Distribution;
use App\Models\DistributionItem;
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
                'type' => Outlet::TYPE_PUSAT,
                'address' => 'Jl. Contoh No. 1, Jakarta',
                'is_active' => true,
            ],
        );

        $outletA = Outlet::updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Outlet Bandung'],
            [
                'type' => Outlet::TYPE_OUTLET,
                'address' => 'Jl. Asia Afrika No. 10, Bandung',
                'is_active' => true,
            ],
        );

        $outletB = Outlet::updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Outlet Surabaya'],
            [
                'type' => Outlet::TYPE_OUTLET,
                'address' => 'Jl. Pemuda No. 25, Surabaya',
                'is_active' => true,
            ],
        );

        $this->command->info('Outlets created: ' . $pusat->name . ', ' . $outletA->name . ', ' . $outletB->name);

        // Get products for distribution
        $products = Product::where('tenant_id', $tenant->id)->get();

        if ($products->isEmpty()) {
            $this->command->info('No products found. Skipping distribution seed.');

            return;
        }

        // Create sample distributions
        $this->seedDistribution($tenant, $pusat, $outletA, $products, 'Matcha Latte', 50, 'Distribusi awal ke Outlet Bandung');
        $this->seedDistribution($tenant, $pusat, $outletB, $products, 'Kopi Susu', 30, 'Distribusi awal ke Outlet Surabaya');

        $this->command->info('Sample distributions created.');
    }

    private function seedDistribution(
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
            'distributed_at' => now()->subDays(3),
            'created_by' => $tenant->users()->first()?->id,
        ]);

        DistributionItem::create([
            'distribution_id' => $distribution->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit' => $product->unit,
        ]);

        // Update outlet inventory
        OutletInventory::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'outlet_id' => $to->id,
                'product_id' => $product->id,
            ],
            [
                'quantity' => $quantity,
                'unit' => $product->unit,
                'last_updated' => now(),
            ],
        );
    }
}
