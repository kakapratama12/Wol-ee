<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bot_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_user_id');
            $table->string('entity_type'); // product, ingredient, recipe, transaction, sale, invoice, partner, expense
            $table->unsignedBigInteger('entity_id')->nullable(); // FK ke tabel asli
            $table->text('raw_input'); // apa yang user ketik di Telegram
            $table->json('parsed_data'); // hasil AI parse
            $table->string('status')->default('active'); // active, archived
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'entity_type']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_inputs');
    }
};
