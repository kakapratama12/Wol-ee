<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_runs', function (Blueprint $table) {
            $table->integer('yield_actual')->nullable()->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('production_runs', function (Blueprint $table) {
            $table->integer('yield_actual')->nullable(false)->default(0)->change();
        });
    }
};
