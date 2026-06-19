<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            // raw_material = bahan baku (tepung, butter), finished_goods = produk jadi (roti, kue)
            $table->string('item_type')->default('raw_material')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('item_type');
        });
    }
};
