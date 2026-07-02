<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 4)->default(0);
            $table->string('unit')->default('gram');
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_inventory');
    }
};
