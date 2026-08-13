<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Прямая ссылка строки протокола на заявку.
 *
 * Протокол исторически связан с участником через `team_id`, но у сборных
 * и индивидуальных экипажей команды нет — личный рейтинг по такой строке
 * начислить некому. Ссылка на заявку даёт калькулятору состав экипажа
 * напрямую (@see App\Services\RatingCalculator::crewByRegattaEntry()).
 *
 * Nullable: у существующих строк и у протоколов, импортированных без заявок,
 * связь остаётся командной.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->foreignUuid('regatta_entry_id')->nullable()->after('team_id')
                ->constrained('regatta_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('regatta_entry_id');
        });
    }
};
