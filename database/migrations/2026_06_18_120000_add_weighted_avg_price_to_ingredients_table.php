<?php

use App\Models\Ingredient;
use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->decimal('weighted_avg_price', 14, 4)->default(0)->after('unit_price');
        });

        Ingredient::query()->eachById(function (Ingredient $ingredient) {
            $weightedAvg = (float) $ingredient->unit_price;
            $stock = 0.0;

            $transactions = Transaction::query()
                ->where('ingredient_id', $ingredient->id)
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get(['quantity', 'unit_price']);

            foreach ($transactions as $transaction) {
                $qty = (float) $transaction->quantity;
                $price = (float) $transaction->unit_price;
                $totalValue = ($stock * $weightedAvg) + ($qty * $price);
                $stock += $qty;
                $weightedAvg = $stock > 0 ? round($totalValue / $stock, 4) : $price;
            }

            if ($transactions->isEmpty()) {
                $weightedAvg = (float) $ingredient->unit_price;
            }

            $ingredient->update(['weighted_avg_price' => $weightedAvg]);
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('weighted_avg_price');
        });
    }
};
