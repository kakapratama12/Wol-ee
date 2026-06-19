<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_user_id');
            $table->date('usage_date');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'telegram_user_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_ai_usages');
    }
};
