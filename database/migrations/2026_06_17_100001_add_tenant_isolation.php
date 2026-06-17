<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $scopedTables = [
        'suppliers',
        'ingredients',
        'products',
        'recipe_items',
        'stock_movements',
        'transactions',
        'sales',
        'price_histories',
        'expenses',
    ];

    public function up(): void
    {
        $tenantId = DB::table('tenants')->insertGetId([
            'name' => 'Kafe Contoh',
            'slug' => 'kafe-contoh',
            'plan' => 'free',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        DB::table('users')->update(['tenant_id' => $tenantId]);

        foreach ($this->scopedTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tenantId) {
                $table->foreignId('tenant_id')->default($tenantId)->after('id')->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->unique(['tenant_id', 'email']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name']);
            $table->unique('name');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name']);
            $table->unique('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
            $table->unique('email');
        });

        foreach (array_reverse($this->scopedTables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        DB::table('tenants')->where('slug', 'kafe-contoh')->delete();
    }
};
