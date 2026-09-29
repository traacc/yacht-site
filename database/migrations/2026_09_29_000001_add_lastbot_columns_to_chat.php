<?php

declare(strict_types=1);

use App\Actions\LastBot\ForwardMessageToLastBotAction;
use App\Actions\LastBot\SyncLastBotThreadAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Зеркалирование обращений в поддержку в LastBot.
 *
 * conversations.lastbot_* — диалог и контакт на стороне LastBot;
 * chat_messages.lastbot_message_id — id пришедшего оттуда ответа (защита от
 * повторного импорта), lastbot_forwarded_at — наше сообщение уже отправлено.
 *
 * @see ForwardMessageToLastBotAction
 * @see SyncLastBotThreadAction
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('lastbot_thread_id', 64)->nullable()->after('support_read_at');
            $table->string('lastbot_contact_uuid', 64)->nullable()->after('lastbot_thread_id');

            $table->index('lastbot_thread_id');
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->string('lastbot_message_id', 64)->nullable()->after('body');
            $table->timestamp('lastbot_forwarded_at')->nullable()->after('lastbot_message_id');

            $table->unique(['conversation_id', 'lastbot_message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropUnique(['conversation_id', 'lastbot_message_id']);
            $table->dropColumn(['lastbot_message_id', 'lastbot_forwarded_at']);
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex(['lastbot_thread_id']);
            $table->dropColumn(['lastbot_thread_id', 'lastbot_contact_uuid']);
        });
    }
};
