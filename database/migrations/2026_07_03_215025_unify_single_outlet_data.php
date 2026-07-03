<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Unify single-outlet tenants: create outlet + migrate stock to outlet_inventories.
     * Additive only — no DROP/TRUNCATE. Safe for production.
     */
    public function up(): void
    {
        $singleTenants = DB::table('tenants')
            ->where('business_type', 'single')
            ->get();

        foreach ($singleTenants as $tenant) {
            // 1. Create outlet for this tenant (if not exists)
            $outletExists = DB::table('outlet_inventory')
                ->where('tenant_id', $tenant->id)
                ->exists();

            $outletId = DB::table('outlets')
                ->where('tenant_id', $tenant->id)
                ->value('id');

            if (! $outletId) {
                $outletId = DB::table('outlets')->insertGetId([
                    'tenant_id' => $tenant->id,
                    'name' => $tenant->name,
                    'type' => 'outlet',
                    'address' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 2. Migrate ingredients.current_stock → outlet_inventories (all ingredients, including negative stock)
            $ingredients = DB::table('ingredients')
                ->where('tenant_id', $tenant->id)
                ->get();

            foreach ($ingredients as $ingredient) {
                $exists = DB::table('outlet_inventory')
                    ->where('tenant_id', $tenant->id)
                    ->where('outlet_id', $outletId)
                    ->where('ingredient_id', $ingredient->id)
                    ->whereNull('product_id')
                    ->exists();

                if (! $exists) {
                    DB::table('outlet_inventory')->insert([
                        'tenant_id' => $tenant->id,
                        'outlet_id' => $outletId,
                        'ingredient_id' => $ingredient->id,
                        'product_id' => null,
                        'quantity' => $ingredient->current_stock,
                        'unit' => $ingredient->base_unit,
                        'last_updated' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // 3. Assign staff (kasir) to outlet
            DB::table('users')
                ->where('tenant_id', $tenant->id)
                ->where('role', 'staff')
                ->whereNull('outlet_id')
                ->update(['outlet_id' => $outletId]);

            // 4. Update cashier_sessions with outlet_id (if null)
            DB::table('cashier_sessions')
                ->where('tenant_id', $tenant->id)
                ->whereNull('outlet_id')
                ->update(['outlet_id' => $outletId]);

            // 5. Update pos_orders with outlet_id (if null)
            DB::table('pos_orders')
                ->where('tenant_id', $tenant->id)
                ->whereNull('outlet_id')
                ->update(['outlet_id' => $outletId]);
        }
    }

    public function down(): void
    {
        // Intentionally empty — this is a one-way data migration.
        // Reverse would require knowing which outlet was auto-created,
        // which is not safe to assume.
    }
};
