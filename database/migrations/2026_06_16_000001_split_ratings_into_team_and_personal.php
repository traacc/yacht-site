<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Разделяем единую таблицу ratings (с дискриминатором rating_type)
 * на две отдельные: team_ratings и personal_ratings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->decimal('total_points', 12, 3)->default(0);
            $table->unsignedSmallInteger('rank_position')->nullable();
            $table->timestamps();

            // Один командный рейтинг на сезон
            $table->unique(['season_id', 'team_id']);
        });

        Schema::create('personal_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('total_points', 12, 3)->default(0);
            $table->unsignedSmallInteger('rank_position')->nullable();
            $table->timestamps();

            // Один личный рейтинг на сезон
            $table->unique(['season_id', 'user_id']);
        });

        // Переносим существующие данные, сохраняя id и временные метки
        if (Schema::hasTable('ratings')) {
            DB::statement("
                INSERT INTO team_ratings (id, season_id, team_id, total_points, rank_position, created_at, updated_at)
                SELECT id, season_id, team_id, total_points, rank_position, created_at, updated_at
                FROM ratings
                WHERE rating_type = 'team' AND team_id IS NOT NULL
            ");

            DB::statement("
                INSERT INTO personal_ratings (id, season_id, user_id, total_points, rank_position, created_at, updated_at)
                SELECT id, season_id, user_id, total_points, rank_position, created_at, updated_at
                FROM ratings
                WHERE rating_type = 'personal' AND user_id IS NOT NULL
            ");

            Schema::dropIfExists('ratings');
        }
    }

    public function down(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->enum('rating_type', ['team', 'personal']);
            $table->decimal('total_points', 12, 3)->default(0);
            $table->unsignedSmallInteger('rank_position')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'team_id']);
            $table->unique(['season_id', 'user_id']);
        });

        DB::statement("
            INSERT INTO ratings (id, season_id, team_id, user_id, rating_type, total_points, rank_position, created_at, updated_at)
            SELECT id, season_id, team_id, NULL, 'team', total_points, rank_position, created_at, updated_at
            FROM team_ratings
        ");

        DB::statement("
            INSERT INTO ratings (id, season_id, team_id, user_id, rating_type, total_points, rank_position, created_at, updated_at)
            SELECT id, season_id, NULL, user_id, 'personal', total_points, rank_position, created_at, updated_at
            FROM personal_ratings
        ");

        Schema::dropIfExists('personal_ratings');
        Schema::dropIfExists('team_ratings');
    }
};
