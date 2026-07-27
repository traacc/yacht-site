<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Один аккаунт на сайте — один чат в Telegram (уникальность с обеих сторон).
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            // chat_id помещается в int64, но храним строкой: не зависим от разрядности PHP.
            $table->string('chat_id', 32)->unique();
            $table->string('username', 64)->nullable();
            $table->string('first_name', 128)->nullable();
            $table->timestamp('linked_at');
            // Заполняется, когда Telegram ответил «bot was blocked by the user» и т.п.
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_accounts');
    }
};
