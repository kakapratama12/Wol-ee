<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlet_inventory', function (Blueprint $table) {
            $table->foreignId('ingredient_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        // Update unique constraint to include ingredient_id
        Schema::table('outlet_inventory', function (Blueprint $table) {
            $table->dropUnique('outlet_inventory_tenant_id_outlet_id_product_id_unique');
            $table->unique(['tenant_id', 'outlet_id', 'product_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::table('outlet_inventory', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'outlet_id', 'product_id', 'ingredient_id']);
            $table->dropConstrainedForeignId('ingredient_id');
            $table->unique(['tenant_id', 'outlet_id', 'product_id']);
        });
    }
};
