<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing freeform categories to the new structured values
        // Original categories (Listrik, Sewa, Internet, Gaji, Marketing, Lainnya) → operasional
        DB::table('expenses')
            ->whereNotIn('category', ['bahan_baku', 'operasional', 'overhead'])
            ->update(['category' => 'operasional']);

        // Set default value for the column
        Schema::table('expenses', function ($table) {
            $table->string('category')->default('bahan_baku')->change();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function ($table) {
            $table->string('category')->nullable()->change();
        });
    }
};
