<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_user_id');
            $table->string('plan');
            $table->string('provider');
            $table->string('model')->nullable();
            $table->string('status');
            $table->string('error_code')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->timestamp('requested_at')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'requested_at']);
            $table->index(['provider', 'requested_at']);
            $table->index(['plan', 'requested_at']);
            $table->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_ai_requests');
    }
};
