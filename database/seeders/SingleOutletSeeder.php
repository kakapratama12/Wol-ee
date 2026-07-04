<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletInventory;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class SingleOutletSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'chockles')->first();

        if (! $tenant) {
            $this->command->info('Tenant Chockles not found. Creating...');

            $tenant = Tenant::create([
                'name' => 'Chockles',
                'slug' => 'chockles',
                'business_type' => 'single',
                'plan' => Tenant::PLAN_FREE,
                'status' => Tenant::STATUS_ACTIVE,
            ]);
        }

        // Ensure single business_type
        $tenant->update(['business_type' => 'single']);

        $outlet = $this->seedOutlet($tenant);
        $this->seedUsers($tenant, $outlet);
        $ingredients = $this->seedIngredients($tenant, $outlet);
        $this->seedPriceHistory($tenant, $ingredients);
        $products = $this->seedProducts($tenant, $ingredients);
        $this->seedActivity($tenant, $outlet, $ingredients, $products);
        $this->seedExpenses($tenant, $outlet);

        $this->command->info('✅ Single outlet demo (Chockles) seeded successfully!');
    }

    private function seedOutlet(Tenant $tenant): Outlet
    {
        return Outlet::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Chockles'],
            [
                'type' => 'outlet',
                'address' => 'Jl. Chockles No. 1',
                'is_active' => true,
            ],
        );
    }

    private function seedUsers(Tenant $tenant, Outlet $outlet): void
    {
        // Owner
        User::updateOrCreate(
            ['email' => 'owner@chockles.test', 'tenant_id' => $tenant->id],
            [
                'name' => 'Owner Chockles',
                'password' => Hash::make('password'),
                'role' => User::ROLE_PENGELOLA,
            ],
        );

        // Staff / Kasir — assigned to the single outlet
        User::updateOrCreate(
            ['email' => 'kasir@chockles.test', 'tenant_id' => $tenant->id],
            [
                'name' => 'Kasir Chockles',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STAFF,
                'outlet_id' => $outlet->id,
            ],
        );

        $this->command->info('Users seeded: owner@chockles.test, kasir@chockles.test (password: password)');
    }

    /**
     * @return array<string, Ingredient>
     */
    private function seedIngredients(Tenant $tenant, Outlet $outlet): array
    {
        $rows = [
            ['Tepung Terigu', 'gramasi', 'g', 18, 5000, 800],
            ['Mentega', 'gramasi', 'g', 85, 2000, 400],
            ['Gula Pasir', 'gramasi', 'g', 14, 4000, 600],
            ['Telur', 'gramasi', 'butir', 2200, 150, 25],
            ['Coklat Bubuk', 'gramasi', 'g', 120, 1500, 200],
            ['Susu Cair', 'gramasi', 'ml', 16, 8000, 1500],
            ['Kopi Arabika', 'gramasi', 'g', 180, 2000, 300],
            ['Vanili', 'packaged', 'sachet', 3000, 80, 15],
        ];

        $ingredients = [];
        foreach ($rows as [$name, $type, $unit, $price, $stock, $min]) {
            $ingredient = Ingredient::withoutGlobalScope('tenant')->updateOrCreate(
                ['name' => $name, 'tenant_id' => $tenant->id],
                [
                    'unit_type' => $type,
                    'base_unit' => $unit,
                    'unit_price' => $price,
                    'current_stock' => $stock,
                    'minimum_stock' => $min,
                ],
            );

            // Populate outlet_inventories from current_stock
            OutletInventory::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $outlet->id,
                    'ingredient_id' => $ingredient->id,
                    'product_id' => null,
                ],
                [
                    'quantity' => $stock,
                    'unit' => $unit,
                    'last_updated' => now(),
                ],
            );

            $ingredients[$name] = $ingredient;
        }

        return $ingredients;
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     */
    private function seedPriceHistory(Tenant $tenant, array $ingredients): void
    {
        $past = [
            'Tepung Terigu' => 15,
            'Mentega' => 75,
            'Kopi Arabika' => 160,
        ];

        foreach ($ingredients as $name => $ingredient) {
            PriceHistory::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'ingredient_id' => $ingredient->id,
                    'recorded_at' => Carbon::now()->subMonths(2)->toDateString(),
                    'tenant_id' => $tenant->id,
                ],
                ['unit_price' => $past[$name] ?? $ingredient->unit_price],
            );
            PriceHistory::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'ingredient_id' => $ingredient->id,
                    'recorded_at' => Carbon::today()->toDateString(),
                    'tenant_id' => $tenant->id,
                ],
                ['unit_price' => $ingredient->unit_price],
            );
        }
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     * @return array<string, Product>
     */
    private function seedProducts(Tenant $tenant, array $ingredients): array
    {
        $definitions = [
            'Brownies Coklat' => ['pcs', 18000, ['Tepung Terigu' => 60, 'Mentega' => 30, 'Gula Pasir' => 40, 'Telur' => 1, 'Coklat Bubuk' => 15]],
            'Kopi Susu' => ['cup', 22000, ['Kopi Arabika' => 15, 'Susu Cair' => 150, 'Gula Pasir' => 10]],
            'Croissant Butter' => ['pcs', 15000, ['Tepung Terigu' => 80, 'Mentega' => 40, 'Telur' => 1, 'Gula Pasir' => 8]],
            'Cookies Coklat' => ['pcs', 8000, ['Tepung Terigu' => 30, 'Mentega' => 20, 'Gula Pasir' => 15, 'Coklat Bubuk' => 10, 'Vanili' => 0.5]],
        ];

        $products = [];
        foreach ($definitions as $name => [$unit, $price, $recipe]) {
            $product = Product::withoutGlobalScope('tenant')->updateOrCreate(
                ['name' => $name, 'tenant_id' => $tenant->id],
                ['unit' => $unit, 'selling_price' => $price, 'is_active' => true],
            );

            $product->recipeItems()->delete();
            foreach ($recipe as $ingredientName => $qty) {
                RecipeItem::withoutGlobalScope('tenant')->create([
                    'product_id' => $product->id,
                    'ingredient_id' => $ingredients[$ingredientName]->id,
                    'quantity' => $qty,
                    'tenant_id' => $tenant->id,
                ]);
            }

            $products[$name] = $product;
        }

        return $products;
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     * @param  array<string, Product>  $products
     */
    private function seedActivity(Tenant $tenant, Outlet $outlet, array $ingredients, array $products): void
    {
        $owner = User::where('email', 'owner@chockles.test')->where('tenant_id', $tenant->id)->first();
        auth()->login($owner);
        $inventory = app(InventoryService::class);
        $sales = app(SaleService::class);

        // Some purchases
        $inventory->recordPurchase($ingredients['Tepung Terigu'], 3000, 18, 'web', null, 'Restok tepung', Carbon::now()->subDays(12));
        $inventory->recordPurchase($ingredients['Susu Cair'], 4000, 16, 'web', null, 'Restok susu', Carbon::now()->subDays(8));
        $inventory->recordPurchase($ingredients['Kopi Arabika'], 1000, 180, 'web', null, 'Restok kopi', Carbon::now()->subDays(5));

        // Sales over last 7 days
        $plan = [
            'Brownies Coklat' => [8, 6, 10, 7, 9, 5, 12],
            'Kopi Susu' => [15, 12, 18, 14, 20, 10, 22],
            'Croissant Butter' => [5, 8, 6, 9, 7, 4, 10],
            'Cookies Coklat' => [10, 8, 12, 6, 14, 9, 16],
        ];

        foreach ($plan as $productName => $quantities) {
            foreach ($quantities as $i => $qty) {
                $sales->record(
                    product: $products[$productName],
                    quantity: $qty,
                    source: 'web',
                    occurredAt: Carbon::now()->subDays(6 - $i),
                );
            }
        }

        $this->command->info('Activity seeded: purchases + 7 days of sales');
    }

    private function seedExpenses(Tenant $tenant, Outlet $outlet): void
    {
        $now = Carbon::now();
        $rows = [
            ['operasional', 'Listrik & Air', 850000],
            ['operasional', 'Sewa Tempat', 3000000],
            ['operasional', 'Gaji 2 Staff', 4000000],
            ['overhead', 'Internet WiFi', 350000],
            ['logistik', 'Ongkir Bahan Baku', 150000],
        ];

        foreach ($rows as [$category, $desc, $amount]) {
            Expense::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'category' => $category,
                    'description' => $desc,
                    'period_month' => $now->month,
                    'period_year' => $now->year,
                    'tenant_id' => $tenant->id,
                ],
                ['amount' => $amount, 'outlet_id' => $outlet->id],
            );
        }

        $this->command->info('Expenses seeded: 5 items');
    }
}
