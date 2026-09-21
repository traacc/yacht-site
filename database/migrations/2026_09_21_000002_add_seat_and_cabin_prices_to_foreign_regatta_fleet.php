<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Полный набор цен на уровне флота: место, каюта, лодка целиком.
 *
 * До сих пор цена места жила только у лодки, а цена каюты — только у самой
 * регаты, одна на весь флот. По ТЗ цены вводятся там же, где остальная
 * спецификация: у дивизиона-флота — один раз на все его лодки, у списка
 * конкретных лодок — у каждой своя. Поэтому обе колонки появляются на
 * дивизионе, а `cabin_price` — ещё и на лодке, рядом с существующей
 * `seat_price` (@see App\Models\ForeignRegattaYacht::spec() — пустое поле
 * лодки берётся у дивизиона).
 *
 * Цены — целые рубли в валюте `currency`, как и `price`: бэкфилл не нужен,
 * незаполненная цена означает «такой вариант не продаётся».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foreign_regatta_divisions', function (Blueprint $table): void {
            $table->unsignedInteger('seat_price')->nullable()->after('price_unit');
            $table->unsignedInteger('cabin_price')->nullable()->after('seat_price');
        });

        Schema::table('foreign_regatta_yachts', function (Blueprint $table): void {
            $table->unsignedInteger('cabin_price')->nullable()->after('seat_price');
        });
    }

    public function down(): void
    {
        Schema::table('foreign_regatta_divisions', function (Blueprint $table): void {
            $table->dropColumn(['seat_price', 'cabin_price']);
        });

        Schema::table('foreign_regatta_yachts', function (Blueprint $table): void {
            $table->dropColumn('cabin_price');
        });
    }
};
