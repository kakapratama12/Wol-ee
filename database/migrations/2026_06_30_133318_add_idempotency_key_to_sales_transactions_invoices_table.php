<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('idempotency_key', 36)->nullable()->after('id');
            $table->unique('idempotency_key');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 36)->nullable()->after('id');
            $table->unique('idempotency_key');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('idempotency_key', 36)->nullable()->after('id');
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
