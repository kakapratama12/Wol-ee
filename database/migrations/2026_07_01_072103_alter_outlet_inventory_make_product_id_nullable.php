<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlet_inventory', function (Blueprint $table) {
            // Drop old unique constraint first
            $table->dropUnique(['tenant_id', 'outlet_id', 'product_id', 'ingredient_id']);
            
            // Make product_id nullable
            $table->foreignId('product_id')->nullable()->change();
            
            // Recreate unique constraint (PostgreSQL treats NULL as unique, so this works)
            $table->unique(['tenant_id', 'outlet_id', 'product_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::table('outlet_inventory', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'outlet_id', 'product_id', 'ingredient_id']);
            $table->foreignId('product_id')->nullable(false)->change();
            $table->unique(['tenant_id', 'outlet_id', 'product_id', 'ingredient_id']);
        });
    }
};
