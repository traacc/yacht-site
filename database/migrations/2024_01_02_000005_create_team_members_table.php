<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // Роль внутри команды
            $table->enum('role', ['organizer', 'team_admin', 'member'])->default('member');
            // Статус приглашения
            $table->enum('status', ['invited', 'active', 'declined'])->default('invited');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            // Один пользователь — одна запись в команде
            $table->unique(['team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
