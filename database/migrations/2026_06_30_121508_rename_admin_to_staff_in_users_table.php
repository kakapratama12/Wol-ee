<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'staff']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'staff')
            ->update(['role' => 'admin']);
    }
};
