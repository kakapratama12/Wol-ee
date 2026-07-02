<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('to_outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('distributed_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'distributed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributions');
    }
};
