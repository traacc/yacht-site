<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('race_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('regatta_events')->cascadeOnDelete();
            $table->foreignUuid('regatta_entry_id')->constrained('regatta_entries')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->nullable();
            $table->decimal('points', 8, 3)->default(0);
            // Флаги судейских пенальти: DNF, DNS, DSQ и т.п.
            $table->string('penalty_code', 10)->nullable();
            $table->timestamps();

            // Один результат команды на гонку
            $table->unique(['event_id', 'regatta_entry_id']);
            $table->index('regatta_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('race_results');
    }
};
