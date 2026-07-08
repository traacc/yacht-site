<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Расширяет снапшот строки результата составом экипажа и разбивкой по гонкам.
 *
 * Дополняет team_name/yacht_name/sail_number/captain_name (см. предыдущую
 * миграцию). При удалении команды и её заявки модалки «Состав команды» и
 * «Результаты по гонкам» строятся из живой заявки, которая исчезает; поэтому
 * замораживаем их содержимое прямо в строке результата.
 *
 * Формат совпадает с тем, что строит App\Livewire\RegattaResults:
 *  - crew_snapshot:  [ {id, name, birthday, rank, avatar, role}, ... ]
 *  - race_breakdown: [ {num, name, pos, pts}, ... ]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->json('crew_snapshot')->nullable()->after('captain_name');
            $table->json('race_breakdown')->nullable()->after('crew_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->dropColumn(['crew_snapshot', 'race_breakdown']);
        });
    }
};
