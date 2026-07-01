<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('tenants')->orderBy('id')->each(function (object $tenant) use ($now): void {
            $exists = DB::table('branches')
                ->where('tenant_id', $tenant->id)
                ->exists();

            if ($exists) {
                return;
            }

            DB::table('branches')->insert([
                'tenant_id' => $tenant->id,
                'name' => 'Cabang Utama',
                'address' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        DB::table('branches')->where('name', 'Cabang Utama')->delete();
    }
};
