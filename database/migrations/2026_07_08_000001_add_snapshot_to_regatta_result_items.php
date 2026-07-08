<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Денормализованный снапшот участника в строке результата регаты.
 *
 * Итоговые результаты команды должны переживать удаление самой команды и её
 * заявки. Раньше строки regatta_result_items ссылались на teams/yachts через
 * restrictOnDelete, а удаление заявки уничтожало строки (см.
 * RegattaEntryResultObserver). Теперь:
 *  - имя команды, название яхты и парусный номер сохраняются прямо в строке;
 *  - team_id становится nullable, а внешние ключи — nullOnDelete, чтобы при
 *    физическом удалении команды/яхты строка уцелела с обнулённой ссылкой.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->string('team_name')->nullable()->after('team_id');
            $table->string('yacht_name')->nullable()->after('yacht_id');
            $table->string('sail_number')->nullable()->after('yacht_name');
            $table->string('captain_name')->nullable()->after('sail_number');
        });

        // Снимаем restrict-ключи, чтобы поменять поведение и nullability.
        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['yacht_id']);
        });

        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->uuid('team_id')->nullable()->change();
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('yacht_id')->references('id')->on('yachts')->nullOnDelete();
        });

        // Бэкфилл снимка для уже существующих строк — чтобы историю не потерять,
        // если команду/яхту удалят раньше, чем строка будет пересохранена.
        // teams/yachts джойним по id даже для soft-deleted (нужно историческое имя).
        DB::statement(<<<'SQL'
            UPDATE regatta_result_items i
            JOIN teams t ON t.id = i.team_id
            SET i.team_name = t.name
            WHERE i.team_name IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE regatta_result_items i
            JOIN yachts y ON y.id = i.yacht_id
            SET i.yacht_name  = COALESCE(i.yacht_name, y.name),
                i.sail_number = COALESCE(i.sail_number, y.vfps_number)
            WHERE i.yacht_id IS NOT NULL
              AND (i.yacht_name IS NULL OR i.sail_number IS NULL)
        SQL);
    }

    public function down(): void
    {
        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['yacht_id']);
        });

        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->uuid('team_id')->nullable(false)->change();
            $table->foreign('team_id')->references('id')->on('teams')->restrictOnDelete();
            $table->foreign('yacht_id')->references('id')->on('yachts')->restrictOnDelete();
        });

        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->dropColumn(['team_name', 'yacht_name', 'sail_number', 'captain_name']);
        });
    }
};
