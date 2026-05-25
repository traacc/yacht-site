<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regatta_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('regatta_id')->constrained('regattas')->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained('teams')->restrictOnDelete();
            // Яхта может быть назначена позже
            $table->foreignUuid('yacht_id')->nullable()->constrained('yachts')->nullOnDelete();
            // Статус заявки
            $table->enum('status', ['pending', 'approved', 'rejected', 'withdrawn'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            // Одна команда — одна заявка на регату
            $table->unique(['regatta_id', 'team_id']);
            // Индекс для поиска занятости яхты
            $table->index(['yacht_id', 'regatta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regatta_entries');
    }
};
