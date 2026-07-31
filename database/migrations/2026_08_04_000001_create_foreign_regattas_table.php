<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Регаты за рубежом (ТЗ 3-го этапа, п. 7).
 *
 * Отдельная таблица, а не флаг у `regattas`: соревновательная регата завязана
 * на рейтинги, протоколы, заявки команд и внешний API, а здесь нужен контент —
 * цены за место и каюту, варианты участия, чартерный флот и галерея.
 *
 * Заявки на участие живут в `service_requests` и ссылаются сюда через
 * morph-колонки `subject` — как у походов.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foreign_regattas', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Сезон нужен, чтобы регата попала в общий календарь сезона.
            // Nullable: если сезона на этот год ещё нет, календарь найдёт
            // регату по году date_start (@see App\Services\SeasonCalendar).
            $table->foreignUuid('season_id')->nullable()->constrained('seasons')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            // HTML из RichEditor: описание регаты и «Маршрут и расписание».
            $table->longText('content')->nullable();
            $table->longText('schedule')->nullable();

            $table->string('country')->nullable();
            $table->string('region')->nullable();
            // Одной строкой для карточки: «Сплит — Хвар — Вис — Сплит».
            $table->string('route_summary')->nullable();
            // «Флот» текстом: на чём идёт регата, если чартерный список не ведётся.
            $table->text('fleet_note')->nullable();

            // Дата начала обязательна: по ней регата делится на предстоящие и прошедшие.
            $table->date('date_start');
            $table->date('date_end')->nullable();

            // Какие варианты участия предлагаются: seat / cabin / yacht.
            $table->json('participation_options')->nullable();

            // Цены — целые рубли (как у туров и объявлений): витринные величины.
            $table->unsignedInteger('price_per_seat')->nullable();
            $table->unsignedInteger('price_per_cabin')->nullable();
            $table->text('price_note')->nullable();

            // Ссылки на видео с подписями: [{url, caption}] — как у туров.
            $table->json('video_links')->nullable();

            $table->boolean('is_published')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'date_start']);
            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foreign_regattas');
    }
};
