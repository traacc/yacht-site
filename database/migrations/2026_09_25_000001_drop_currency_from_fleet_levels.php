<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Валюта выбирается один раз — у регаты.
 *
 * Своя валюта у дивизиона и у лодки появилась «на всякий случай», а на деле
 * дала три места выбора, цепочку наследования и возможность смешать евро с
 * долларами внутри одного флота. Чартер одной регаты считают в одной валюте,
 * поэтому колонки уходят, а `foreign_regattas.currency` остаётся единственной.
 *
 * Данные не теряются по смыслу: заполненные значения совпадали с валютой своей
 * регаты. Откат возвращает пустые колонки — прежние значения не восстанавливает.
 */
return new class extends Migration
{
    private const TABLES = [
        'foreign_regatta_divisions',
        'foreign_regatta_yachts',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('currency');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('currency', 3)->nullable()->after('price_note');
            });
        }
    }
};
