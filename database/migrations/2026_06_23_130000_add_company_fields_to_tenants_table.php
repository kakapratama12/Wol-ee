<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('address')->nullable()->after('name');
            $table->string('phone', 50)->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
            $table->string('bank_name')->nullable()->after('email');
            $table->string('bank_account')->nullable()->after('bank_name');
            $table->string('bank_account_name')->nullable()->after('bank_account');
            $table->string('logo')->nullable()->after('bank_account_name');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'phone',
                'email',
                'bank_name',
                'bank_account',
                'bank_account_name',
                'logo',
            ]);
        });
    }
};
