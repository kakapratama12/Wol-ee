<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'modal_awal' | 'modal_tambahan' | 'lainnya'
            $table->decimal('amount', 14, 2);
            $table->string('description')->nullable();
            $table->date('occurred_at');
            $table->timestamps();

            $table->index(['occurred_at']);
            $table->index(['tenant_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_entries');
    }
};
