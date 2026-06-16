<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Riwayat harga bahan baku (per base_unit) untuk Margin Protection
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 14, 4);
            $table->date('recorded_at');
            $table->timestamps();

            $table->index(['ingredient_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
