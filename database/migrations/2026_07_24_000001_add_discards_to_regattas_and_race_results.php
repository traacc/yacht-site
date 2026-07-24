<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            // Выброс худших результатов: 0 — без выброса, 1/2 — сколько худших
            // результатов исключается из зачёта. Существующие регаты остаются
            // с 0, чтобы исторические протоколы не менялись при пересчётах;
            // дефолт «1 выброс» для новых регат задаёт форма админки.
            $table->unsignedTinyInteger('discards_count')->default(0)->after('races_count');
            // После скольких проведённых гонок выбрасывается 1-й и 2-й результат.
            $table->unsignedTinyInteger('discard_1_after_races')->default(6)->after('discards_count');
            $table->unsignedTinyInteger('discard_2_after_races')->default(9)->after('discard_1_after_races');
        });

        Schema::table('race_results', function (Blueprint $table) {
            // Результат выброшен авторасчётом (recomputeItemTotals); ставится и
            // снимается идемпотентно при каждом пересчёте. Судейские выбросы из
            // импорта помечены скобками в points и этим флагом не дублируются.
            $table->boolean('is_discarded')->default(false)->after('points');
        });
    }

    public function down(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            $table->dropColumn(['discards_count', 'discard_1_after_races', 'discard_2_after_races']);
        });

        Schema::table('race_results', function (Blueprint $table) {
            $table->dropColumn('is_discarded');
        });
    }
};
