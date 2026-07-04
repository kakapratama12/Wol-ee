<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Drop old check constraint
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_status_check');
        // Recreate with 'draft' added
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'outstanding'::character varying, 'partial'::character varying, 'paid'::character varying])::text[])))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_status_check');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (((status)::text = ANY ((ARRAY['outstanding'::character varying, 'partial'::character varying, 'paid'::character varying])::text[])))");
    }
};
