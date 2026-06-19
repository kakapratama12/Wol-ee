<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // resep yang dipakai
            $table->integer('batch_count')->default(1); // jumlah batch
            $table->integer('yield_actual'); // yield aktual (pcs/loyang)
            $table->integer('waste_count')->default(0); // jumlah waste (pcs)
            $table->decimal('total_cost', 14, 4)->default(0); // snapshot total cost
            $table->string('notes')->nullable();
            $table->timestamp('produced_at')->useCurrent(); // kapan produksi dilakukan
            $table->timestamps();

            $table->index(['tenant_id', 'produced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_runs');
    }
};
