<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pembelian bahan baku (purchase)
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            // quantity dalam base_unit ingredient
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('total', 14, 2);
            // bot | web
            $table->string('source')->default('web');
            $table->string('note')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
