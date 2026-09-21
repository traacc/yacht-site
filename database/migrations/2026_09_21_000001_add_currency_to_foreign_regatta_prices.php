<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Валюта цен зарубежных регат: чартер за границей считают в евро и долларах.
 *
 * Колонка на каждом уровне, где хранится сумма: у самой регаты (место и каюта),
 * у дивизиона-флота (общая цена его лодок) и у лодки. Значения
 * App\Enums\Currency — строкой, как `price_unit` и `status`.
 *
 * Пусто = рубли: все заведённые до сих пор цены рублёвые, поэтому колонка
 * nullable и бэкфилл не нужен.
 */
return new class extends Migration
{
    private const TABLES = [
        'foreign_regattas',
        'foreign_regatta_divisions',
        'foreign_regatta_yachts',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('currency', 3)->nullable()->after('price_note');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('currency');
            });
        }
    }
};
