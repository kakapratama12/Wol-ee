<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Estimated yield per batch for batch-type products (e.g., 40 croissants per batch)
            $table->integer('estimated_yield_per_batch')->nullable()->after('recipe_type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('estimated_yield_per_batch');
        });
    }
};
