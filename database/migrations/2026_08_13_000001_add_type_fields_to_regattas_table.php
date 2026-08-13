<?php

use App\Enums\RegattaType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Три типа соревнований: клубные, регулярные и выездные.
 *
 * Тип — колонка у `regattas`, а не отдельная модель: все три остаются
 * соревновательными регатами с заявками, протоколами и рейтингами, различаясь
 * только способом заявки и парой денежных полей. Строка, а не MySQL ENUM:
 * enum-колонки в этом проекте требуют сырых MODIFY-миграций и ломают тестовую
 * БД (@see AGENTS.md, «Известные грабли»).
 *
 * Все существующие регаты — клубные, поэтому default совпадает с их смыслом.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            $table->string('type', 20)->default(RegattaType::Club->value)->after('series_id')->index();

            // Регулярные: ассоциация выставляет лодки и продаёт места на них.
            $table->decimal('seat_price', 10, 2)->nullable()->after('entry_fee_amount');
            $table->decimal('boat_price', 10, 2)->nullable()->after('seat_price');
            // Дробные часы допустимы: гоночный день бывает и 4,5 часа.
            $table->decimal('race_hours_per_day', 4, 1)->nullable()->after('boat_price');

            // Выездные: размер экипажа определяется характеристиками лодок регаты.
            $table->unsignedTinyInteger('crew_size_limit')->nullable()->after('race_hours_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'seat_price', 'boat_price', 'race_hours_per_day', 'crew_size_limit']);
        });
    }
};
