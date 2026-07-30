<?php

declare(strict_types=1);

use App\Actions\Chat\SendChatMessageAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сообщение чата может состоять только из вложений, без текста.
 *
 * @see SendChatMessageAction
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->text('body')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->text('body')->nullable(false)->change();
        });
    }
};
