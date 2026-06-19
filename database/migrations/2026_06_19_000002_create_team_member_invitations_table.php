<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_member_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Команда, в которую приглашают участника (станет его новой главной командой)
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();

            // Приглашаемый участник
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // Текущая главная команда участника на момент запроса (снимок)
            $table->foreignUuid('from_team_id')->nullable()->constrained('teams')->nullOnDelete();

            // Капитан, отправивший запрос
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();

            // pending — на рассмотрении, approved — одобрено, rejected — отклонено, withdrawn — отозвано
            $table->enum('status', ['pending', 'approved', 'rejected', 'withdrawn'])->default('pending');

            // Сообщение от капитана и причина отказа
            $table->text('message')->nullable();
            $table->string('rejection_reason')->nullable();

            // Когда участник ответил на запрос
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_member_invitations');
    }
};
