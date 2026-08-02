<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Поля четырёх бирж раздела «Соревнования» (ТЗ 3-го этапа, п. 8.2).
 *
 * Колонки явные, а не json `payload` как у `service_requests`: все они участвуют
 * в серверных фильтрах витрины (@see App\Services\AdvertBoard), а по json
 * фильтровать нельзя. Значения enum'ов хранятся строками — как `type` и
 * `status`, чтобы новая доска или новый вид не требовали миграции.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adverts', function (Blueprint $table): void {
            // App\Enums\AdvertKind — предложение или запрос. Пусто у досок без
            // дуальности (экипажи всегда ищут лодку, владельцы — экипаж).
            $table->string('kind', 16)->nullable()->after('status');

            // App\Enums\AdvertPosition — рулевой / матрос / любая.
            $table->string('position', 16)->nullable()->after('kind');

            // App\Enums\SportCategory — разряд, тот же справочник, что у users.
            $table->string('sport_category', 8)->nullable()->after('position');

            // App\Enums\AdvertPriceUnit — ₽ / ₽ в час / ₽ в сутки.
            $table->string('price_unit', 16)->nullable()->after('price_negotiable');

            // Залог при аренде паруса.
            $table->unsignedBigInteger('deposit')->nullable()->after('price_unit');

            // «На какую яхту», если её нет в реестре: дополняет nullable yacht_id.
            $table->string('yacht_name')->nullable()->after('yacht_id');

            // Второе описание: у экипажей — «какую лодку ищем».
            $table->text('details')->nullable()->after('description');

            // «Когда»: период, на который человек свободен или ищет.
            $table->date('date_from')->nullable()->after('details');
            $table->date('date_to')->nullable()->after('date_from');

            $table->index(['type', 'status', 'kind', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('adverts', function (Blueprint $table): void {
            $table->dropIndex(['type', 'status', 'kind', 'published_at']);
            $table->dropColumn([
                'kind',
                'position',
                'sport_category',
                'price_unit',
                'deposit',
                'yacht_name',
                'details',
                'date_from',
                'date_to',
            ]);
        });
    }
};
