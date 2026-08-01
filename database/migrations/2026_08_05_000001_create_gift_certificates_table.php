<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Каталог подарочных сертификатов (ТЗ 3-го этапа, п. 7).
 *
 * Позиция каталога, а не выданный бланк: здесь описание и цена предложения
 * («сертификат на выход в море»), а конкретный заказ живёт в `service_requests`
 * и ссылается сюда через morph-колонки `subject` — как походы и зарубежные регаты.
 *
 * Цена бывает двух видов: фиксированная (`price`) и диапазонная — тогда
 * заказчик выбирает номинал от `price_min` до `price_max` шагом `price_step`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('title');
            // Отдельной страницы у сертификата нет; slug — якорь на витрине,
            // по нему же ссылается «Объект заявки» в админке.
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            // HTML из RichEditor: подробное описание, что входит в сертификат.
            $table->longText('content')->nullable();
            $table->text('terms')->nullable();

            // Значения App\Enums\CertificatePriceType — строкой, чтобы новый вид
            // цены не требовал ENUM-миграции (как `service_requests.type`).
            $table->string('price_type', 16)->default('fixed');

            // Цены — целые рубли, как у туров и зарубежных регат.
            $table->unsignedInteger('price')->nullable();
            $table->unsignedInteger('price_min')->nullable();
            $table->unsignedInteger('price_max')->nullable();
            // Шаг номинала: из него собирается список сумм в форме заказа.
            $table->unsignedInteger('price_step')->nullable();
            $table->text('price_note')->nullable();

            $table->unsignedSmallInteger('validity_months')->nullable();

            $table->boolean('is_published')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            // Мягкое удаление: подпись объекта в уже оформленном заказе
            // резолвится по этой таблице и не должна пропадать.
            $table->softDeletes();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_certificates');
    }
};
