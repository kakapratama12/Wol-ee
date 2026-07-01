<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE invoice_items ALTER COLUMN quantity TYPE decimal(18,4)');
        DB::statement('ALTER TABLE invoice_items ALTER COLUMN unit_price TYPE decimal(18,4)');
        DB::statement('ALTER TABLE invoice_items ALTER COLUMN total TYPE decimal(18,4)');
        DB::statement('ALTER TABLE invoice_fees ALTER COLUMN value TYPE decimal(18,4)');
        DB::statement('ALTER TABLE invoice_fees ALTER COLUMN amount TYPE decimal(18,4)');
        DB::statement('ALTER TABLE invoices ALTER COLUMN amount TYPE decimal(18,4)');
        DB::statement('ALTER TABLE invoices ALTER COLUMN paid_amount TYPE decimal(18,4)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE invoice_items ALTER COLUMN quantity TYPE decimal(14,4)');
        DB::statement('ALTER TABLE invoice_items ALTER COLUMN unit_price TYPE decimal(14,4)');
        DB::statement('ALTER TABLE invoice_items ALTER COLUMN total TYPE decimal(14,4)');
        DB::statement('ALTER TABLE invoice_fees ALTER COLUMN value TYPE decimal(15,2)');
        DB::statement('ALTER TABLE invoice_fees ALTER COLUMN amount TYPE decimal(15,2)');
        DB::statement('ALTER TABLE invoices ALTER COLUMN amount TYPE decimal(15,2)');
        DB::statement('ALTER TABLE invoices ALTER COLUMN paid_amount TYPE decimal(15,2)');
    }
};
