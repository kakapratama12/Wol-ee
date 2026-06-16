<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // gramasi (g, ml, butir) atau packaged (sachet, pack, botol)
            $table->string('unit_type')->default('gramasi');
            // satuan dasar penyimpanan stok & harga, mis. "g", "ml", "butir", "sachet"
            $table->string('base_unit');
            // harga per base_unit (boleh pecahan, mis. Rp 18/g)
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('current_stock', 14, 4)->default(0);
            $table->decimal('minimum_stock', 14, 4)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
