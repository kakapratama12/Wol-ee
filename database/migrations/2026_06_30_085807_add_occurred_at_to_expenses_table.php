<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->timestamp('occurred_at')->nullable()->after('amount');
        });

        // Migrate existing data: set occurred_at from period_month/period_year
        DB::table('expenses')
            ->whereNull('occurred_at')
            ->orderBy('id')
            ->chunkById(100, function ($expenses) {
                foreach ($expenses as $expense) {
                    DB::table('expenses')
                        ->where('id', $expense->id)
                        ->update([
                            'occurred_at' => $expense->period_year . '-' . str_pad($expense->period_month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00',
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('occurred_at');
        });
    }
};
