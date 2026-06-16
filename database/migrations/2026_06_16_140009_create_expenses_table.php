<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Biaya operasional non-COGS untuk P&L (listrik, sewa, internet, lainnya)
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->timestamps();

            $table->index(['period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
