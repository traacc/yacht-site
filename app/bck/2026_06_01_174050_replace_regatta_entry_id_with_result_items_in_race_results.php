<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Добавляем новую колонку как nullable
        Schema::table('race_results', function (Blueprint $table) {
            $table->uuid('regatta_result_items_id')->nullable()->after('event_id');
        });

        // 2. Перенос данных: race_result → regatta_entry → team → regatta_result_item
        DB::statement('
            UPDATE race_results rr
            INNER JOIN regatta_entries re ON re.id = rr.regatta_entry_id
            INNER JOIN regatta_events ev ON ev.id = rr.event_id
            INNER JOIN regatta_result_items ri
                ON  ri.team_id = re.team_id
                AND ri.regatta_result_id = (
                    SELECT r.id FROM regatta_results r WHERE r.regatta_id = ev.regatta_id LIMIT 1
                )
            SET rr.regatta_result_items_id = ri.id
        ');

        // 3. Удаляем старый внешний ключ, индекс и unique
        Schema::table('race_results', function (Blueprint $table) {
            $table->dropForeign(['regatta_entry_id']);
            $table->dropIndex('race_results_regatta_entry_id_index');
            $table->dropUnique(['event_id', 'regatta_entry_id']);
        });

        // 4. Удаляем колонку regatta_entry_id
        Schema::table('race_results', function (Blueprint $table) {
            $table->dropColumn('regatta_entry_id');
        });

        // 5. Делаем regatta_result_items_id NOT NULL (через raw SQL)
        DB::statement('ALTER TABLE race_results MODIFY COLUMN regatta_result_items_id CHAR(36) NOT NULL');

        // 6. Добавляем внешний ключ, unique и index
        Schema::table('race_results', function (Blueprint $table) {
            $table->foreign('regatta_result_items_id')
                  ->references('id')
                  ->on('regatta_result_items')
                  ->cascadeOnDelete();
            $table->unique(['event_id', 'regatta_result_items_id']);
            $table->index('regatta_result_items_id');
        });
    }

    public function down(): void
    {
        // 1. Добавляем обратно regatta_entry_id как nullable
        Schema::table('race_results', function (Blueprint $table) {
            $table->uuid('regatta_entry_id')->nullable()->after('event_id');
        });

        // 2. Восстанавливаем данные из regatta_result_items → team → regatta_entries
        DB::statement('
            UPDATE race_results rr
            INNER JOIN regatta_result_items ri ON ri.id = rr.regatta_result_items_id
            INNER JOIN regatta_entries re ON re.team_id = ri.team_id
            INNER JOIN regatta_events ev ON ev.id = rr.event_id
            SET rr.regatta_entry_id = re.id
        ');

        // 3. Удаляем новые FK, unique, index и колонку
        Schema::table('race_results', function (Blueprint $table) {
            $table->dropForeign(['regatta_result_items_id']);
            $table->dropIndex('race_results_regatta_result_items_id_index');
            $table->dropUnique(['event_id', 'regatta_result_items_id']);
            $table->dropColumn('regatta_result_items_id');
        });

        // 4. Делаем regatta_entry_id NOT NULL и добавляем ограничения
        DB::statement('ALTER TABLE race_results MODIFY COLUMN regatta_entry_id CHAR(36) NOT NULL');

        Schema::table('race_results', function (Blueprint $table) {
            $table->foreign('regatta_entry_id')
                  ->references('id')
                  ->on('regatta_entries')
                  ->cascadeOnDelete();
            $table->unique(['event_id', 'regatta_entry_id']);
            $table->index('regatta_entry_id');
        });
    }
};