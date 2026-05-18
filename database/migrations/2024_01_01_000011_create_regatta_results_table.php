<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regatta_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('regatta_id')->constrained('regattas')->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained('teams')->restrictOnDelete();
            
            // Итоговые очки с учётом level_coefficient регаты
            $table->decimal('total_points', 10, 3)->default(0);
            $table->unsignedSmallInteger('final_position')->nullable();
            $table->timestamps();

            // Один итог на команду в рамках регаты
            $table->unique(['regatta_id', 'team_id']);
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regatta_results');
    }
};
