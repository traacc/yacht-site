<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // Значения App\Enums\ConversationRole.
            // Операторы поддержки участниками не заводятся: «поддержка» — это роль,
            // а не конкретный человек, её прочтение живёт в conversations.support_read_at.
            $table->string('role', 32);

            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id'], 'conversation_participants_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
