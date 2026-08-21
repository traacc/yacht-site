<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_verification_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // Нормализованный номер 7XXXXXXXXXX: пользователь может сменить
            // телефон, а старый код не должен подтверждать новый номер.
            $table->string('phone', 16);
            // sha256 от кода — как в telegram_link_tokens: plaintext в БД не попадает.
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            // messageUuid провайдера — для разбора недоставок в ЛК i-digital direct.
            $table->string('provider_message_id', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'phone']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verification_codes');
    }
};
