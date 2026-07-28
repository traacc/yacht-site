<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();

            // Пусто у системных сообщений («обращение закрыто») и после удаления автора.
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Значения App\Enums\MessageAuthorRole: от чьего лица написано сообщение.
            // Хранится отдельно от user_id: роль автора в момент отправки не должна
            // меняться задним числом вместе с его системной ролью.
            $table->string('author_role', 32);

            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at'], 'chat_messages_thread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
