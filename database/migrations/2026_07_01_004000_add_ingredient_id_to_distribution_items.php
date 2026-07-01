<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_items', function (Blueprint $table) {
            $table->foreignId('ingredient_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('distribution_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ingredient_id');
        });
    }
};
