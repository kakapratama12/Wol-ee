<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cashier_sessions', function (Blueprint $table) {
            $table->foreignId('outlet_id')->nullable()->change();
        });
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->foreignId('outlet_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashier_sessions', function (Blueprint $table) {
            $table->foreignId('outlet_id')->nullable(false)->change();
        });
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->foreignId('outlet_id')->nullable(false)->change();
        });
    }
};
