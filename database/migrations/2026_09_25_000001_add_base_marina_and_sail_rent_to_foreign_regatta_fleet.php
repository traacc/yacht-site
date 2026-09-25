<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Поля таблицы флота «Данные яхт для публикации»: базовая марина, аренда и
 * депозит спинакера/геннакера.
 *
 * Чартер берёт за парус полных курсов отдельно от лодки, и посетитель
 * сравнивает лодки в том числе по этой строке, поэтому она выведена в
 * колонки, а не оставлена в описании.
 *
 * Колонки заводятся и на дивизионе: у монотипа без выбора яхт марина общая
 * для всех лодок, а у обоих монотипов общие и цены паруса
 * (@see App\Models\ForeignRegattaYacht::spec()).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['foreign_regatta_yachts', 'foreign_regatta_divisions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('base_marina')->nullable()->after('downwind_sail');
                $table->unsignedInteger('downwind_sail_price')->nullable()->after('deposit');
                $table->unsignedInteger('downwind_sail_deposit')->nullable()->after('downwind_sail_price');
            });
        }
    }

    public function down(): void
    {
        foreach (['foreign_regatta_yachts', 'foreign_regatta_divisions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['base_marina', 'downwind_sail_price', 'downwind_sail_deposit']);
            });
        }
    }
};
