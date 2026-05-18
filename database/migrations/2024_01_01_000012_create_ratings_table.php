<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('season_id')->constrained('seasons')->cascadeOnDelete();
            // Ровно одно из двух полей заполнено: либо team, либо user
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            // team — командный рейтинг, personal — личный
            $table->enum('rating_type', ['team', 'personal']);
            $table->decimal('total_points', 12, 3)->default(0);
            $table->unsignedSmallInteger('rank_position')->nullable();
            $table->timestamps();

            // Один командный рейтинг на сезон
            $table->unique(['season_id', 'team_id']);
            // Один личный рейтинг на сезон
            $table->unique(['season_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
