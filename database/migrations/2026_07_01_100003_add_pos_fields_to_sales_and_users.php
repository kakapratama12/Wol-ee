<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('pos_order_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('pos_order_id')->constrained()->nullOnDelete();
            $table->string('status')->default('active')->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_order_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
