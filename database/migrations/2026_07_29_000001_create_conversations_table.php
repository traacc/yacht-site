<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Значения App\Enums\ConversationType и App\Enums\ConversationStatus.
            // Строки, а не ENUM: новый тип диалога (чат по объявлению биржи и т.п.)
            // должен добавляться без миграции.
            $table->string('type', 32);
            $table->string('status', 32);

            $table->string('title')->nullable();

            // Точка расширения: привязка диалога к объявлению биржи, яхте, заявке.
            // Для обращений в поддержку остаётся пустой.
            $table->nullableUuidMorphs('subject');

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('last_message_at')->nullable();

            // Маркер прочтения общего ящика поддержки: открыл один оператор —
            // прочитано для всей команды. Для чатов между пользователями не используется.
            $table->timestamp('support_read_at')->nullable();

            $table->timestamps();

            $table->index(['type', 'status', 'last_message_at'], 'conversations_inbox_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
