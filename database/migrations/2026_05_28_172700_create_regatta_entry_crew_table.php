<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regatta_entry_crew', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('regatta_entry_id')->constrained('regatta_entries')->cascadeOnDelete();
            $table->foreignUuid('team_member_id')->constrained('team_members')->cascadeOnDelete();
            $table->enum('role', ['main', 'reserve'])->default('main');
            $table->timestamps();

            // Один пользователь — одна роль в одной заявке
            $table->unique(['regatta_entry_id', 'team_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regatta_entry_crew');
    }
};