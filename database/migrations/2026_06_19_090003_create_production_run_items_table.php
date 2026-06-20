<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_used', 14, 4); // bahan yang benar-benar terpakai
            $table->decimal('unit_cost_snapshot', 14, 4); // snapshot harga per unit saat produksi
            $table->timestamps();

            $table->unique(['production_run_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_run_items');
    }
};
