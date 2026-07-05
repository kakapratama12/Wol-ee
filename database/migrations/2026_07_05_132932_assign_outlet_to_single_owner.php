<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Assign pengelola users with NULL outlet_id to their tenant's first single outlet
        // This handles accounts created before the unified outlet model
        $users = DB::table('users')
            ->where('role', 'pengelola')
            ->whereNull('outlet_id')
            ->get();

        foreach ($users as $user) {
            $outlet = DB::table('outlets')
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->first();

            if ($outlet) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['outlet_id' => $outlet->id]);
            }
        }
    }

    public function down(): void
    {
        // Reverse: set outlet_id back to NULL for users that were updated
        // (acceptable data loss since this is a migration fix)
        DB::table('users')
            ->where('role', 'pengelola')
            ->whereNotNull('outlet_id')
            ->update(['outlet_id' => null]);
    }
};
