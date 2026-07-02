<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old foreign key and index first
        Schema::table('cashier_sessions', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['branch_id', 'closed_at']);
            $table->renameColumn('branch_id', 'outlet_id');
        });

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->renameColumn('branch_id', 'outlet_id');
        });

        // Add new foreign keys pointing to outlets
        Schema::table('cashier_sessions', function (Blueprint $table) {
            $table->foreign('outlet_id')->references('id')->on('outlets')->cascadeOnDelete();
            $table->index(['outlet_id', 'closed_at']);
        });

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->foreign('outlet_id')->references('id')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cashier_sessions', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropIndex(['outlet_id', 'closed_at']);
            $table->renameColumn('outlet_id', 'branch_id');
        });

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->renameColumn('outlet_id', 'branch_id');
        });

        Schema::table('cashier_sessions', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->index(['branch_id', 'closed_at']);
        });

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });
    }
};
