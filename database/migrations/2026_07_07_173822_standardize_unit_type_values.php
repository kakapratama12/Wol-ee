<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Standardize unit_type values:
     * - gramasi (g, kg) → weight
     * - gramasi (ml, l, liter) → volume
     * - gramasi (butir, pcs, pack, sachet, botol) → count
     * - packaged (any) → count
     * - weight, volume, count → keep as is
     */
    public function up(): void
    {
        // Weight units
        $weightUnits = ['g', 'kg', 'gram', 'kilogram', 'ons'];
        DB::table('ingredients')
            ->where('unit_type', 'gramasi')
            ->whereIn('base_unit', $weightUnits)
            ->update(['unit_type' => 'weight']);

        // Volume units
        $volumeUnits = ['ml', 'l', 'liter', 'milliliter'];
        DB::table('ingredients')
            ->where('unit_type', 'gramasi')
            ->whereIn('base_unit', $volumeUnits)
            ->update(['unit_type' => 'volume']);

        // Count units (remaining gramasi + all packaged)
        $countUnits = ['butir', 'pcs', 'pack', 'sachet', 'botol', 'buah', 'ikat', 'box', 'karton'];
        DB::table('ingredients')
            ->where('unit_type', 'gramasi')
            ->whereIn('base_unit', $countUnits)
            ->update(['unit_type' => 'count']);

        // All packaged → count
        DB::table('ingredients')
            ->where('unit_type', 'packaged')
            ->update(['unit_type' => 'count']);

        // Any remaining gramasi with unknown base_unit → count (safe fallback)
        DB::table('ingredients')
            ->where('unit_type', 'gramasi')
            ->update(['unit_type' => 'count']);
    }

    public function down(): void
    // Not reversible — we can't know which 'weight' was originally 'gramasi'
    {
        // No-op: original values are lost after migration
    }
};
