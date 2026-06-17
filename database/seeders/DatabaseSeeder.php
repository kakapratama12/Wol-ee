<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Ingredient;
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

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = $this->seedTenant();
        $this->seedUsers($tenant);
        $ingredients = $this->seedIngredients($tenant);
        $this->seedPriceHistory($tenant, $ingredients);
        $products = $this->seedProducts($tenant, $ingredients);
        $this->seedActivity($tenant, $ingredients, $products);
        $this->seedExpenses($tenant);
    }

    private function seedTenant(): Tenant
    {
        return Tenant::updateOrCreate(
            ['slug' => 'kafe-contoh'],
            [
                'name' => 'Kafe Contoh',
                'plan' => Tenant::PLAN_FREE,
                'status' => Tenant::STATUS_ACTIVE,
            ],
        );
    }

    private function seedUsers(Tenant $tenant): void
    {
        User::updateOrCreate(
            ['email' => 'owner@wol-ee.local', 'tenant_id' => $tenant->id],
            ['name' => 'Owner Wol-ee', 'password' => Hash::make('password'), 'role' => User::ROLE_OWNER],
        );

        User::updateOrCreate(
            ['email' => 'admin@wol-ee.local', 'tenant_id' => $tenant->id],
            ['name' => 'Admin Staff', 'password' => Hash::make('password'), 'role' => User::ROLE_ADMIN],
        );
    }

    /**
     * @return array<string, Ingredient>
     */
    private function seedIngredients(Tenant $tenant): array
    {
        // [name, unit_type, base_unit, unit_price (per base_unit), current_stock, minimum_stock]
        $rows = [
            ['Tepung', 'gramasi', 'g', 20, 8000, 1000],
            ['Susu', 'gramasi', 'ml', 18, 10000, 2000],
            ['Pasta Matcha', 'gramasi', 'g', 250, 1500, 300],
            ['Gula', 'gramasi', 'g', 15, 8000, 1000],
            ['Telur', 'gramasi', 'butir', 2500, 200, 30],
            ['Butter', 'gramasi', 'g', 90, 3000, 500],
            ['Kopi', 'gramasi', 'g', 150, 3000, 500],
            ['Coklat Bubuk', 'packaged', 'sachet', 5000, 120, 20],
        ];

        $ingredients = [];
        foreach ($rows as [$name, $type, $unit, $price, $stock, $min]) {
            $ingredients[$name] = Ingredient::withoutGlobalScope('tenant')->updateOrCreate(
                ['name' => $name, 'tenant_id' => $tenant->id],
                [
                    'unit_type' => $type,
                    'base_unit' => $unit,
                    'unit_price' => $price,
                    'current_stock' => $stock,
                    'minimum_stock' => $min,
                ],
            );
        }

        return $ingredients;
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     */
    private function seedPriceHistory(Tenant $tenant, array $ingredients): void
    {
        $past = [
            'Tepung' => 16,
            'Pasta Matcha' => 220,
            'Butter' => 78,
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
        // name => [unit, selling_price, recipe => [ingredientName => qty(base_unit)]]
        $definitions = [
            'Matcha Latte' => ['cup', 45000, ['Susu' => 200, 'Pasta Matcha' => 20, 'Gula' => 15]],
            'Croissant' => ['pcs', 25000, ['Tepung' => 80, 'Butter' => 40, 'Telur' => 1, 'Gula' => 10]],
            'Kopi Susu' => ['cup', 22000, ['Kopi' => 18, 'Susu' => 150, 'Gula' => 12]],
            'Roti Goreng' => ['pcs', 8000, ['Tepung' => 100, 'Gula' => 5]],
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
    private function seedActivity(Tenant $tenant, array $ingredients, array $products): void
    {
        $owner = User::where('email', 'owner@wol-ee.local')->where('tenant_id', $tenant->id)->first();
        auth()->login($owner);
        $inventory = app(InventoryService::class);
        $sales = app(SaleService::class);

        $inventory->recordPurchase($ingredients['Tepung'], 5000, 20, 'web', null, 'Restok awal', Carbon::now()->subDays(10));
        $inventory->recordPurchase($ingredients['Susu'], 6000, 18, 'web', null, 'Restok awal', Carbon::now()->subDays(8));

        $plan = [
            'Matcha Latte' => [12, 8, 15],
            'Kopi Susu' => [20, 14, 10],
            'Croissant' => [6, 9, 7],
            'Roti Goreng' => [25, 18, 30],
        ];

        foreach ($plan as $productName => $quantities) {
            foreach ($quantities as $i => $qty) {
                $sales->record(
                    product: $products[$productName],
                    quantity: $qty,
                    source: 'web',
                    occurredAt: Carbon::now()->subDays(($i + 1) * 2),
                );
            }
        }
    }

    private function seedExpenses(Tenant $tenant): void
    {
        $now = Carbon::now();
        $rows = [
            ['Listrik', 'Token PLN', 1200000],
            ['Sewa', 'Sewa kedai', 5000000],
            ['Internet', 'WiFi bulanan', 400000],
            ['Gaji', 'Gaji staff', 3500000],
        ];

        foreach ($rows as [$category, $desc, $amount]) {
            Expense::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'category' => $category,
                    'period_month' => $now->month,
                    'period_year' => $now->year,
                    'tenant_id' => $tenant->id,
                ],
                ['description' => $desc, 'amount' => $amount],
            );
        }
    }
}
