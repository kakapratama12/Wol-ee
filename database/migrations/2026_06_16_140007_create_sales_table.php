<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('revenue', 14, 2);
            // COGS snapshot saat penjualan (harga bahan bisa berubah nanti)
            $table->decimal('cogs', 14, 2);
            $table->decimal('profit', 14, 2);
            $table->decimal('margin', 6, 2); // persen
            $table->string('source')->default('web');
            $table->string('note')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
