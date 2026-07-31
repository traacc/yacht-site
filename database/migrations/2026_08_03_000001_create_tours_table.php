<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Яхтенные путешествия и походы (ТЗ 3-го этапа, п. 7).
 *
 * Заявки на участие отдельной таблицы не требуют: они живут в
 * `service_requests` и ссылаются на тур через morph-колонки `subject`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // «На чём»: яхта из реестра либо свободный текст для чартерной
            // лодки, которой в реестре нет («Bavaria 46, Хорватия»).
            $table->foreignUuid('yacht_id')->nullable()->constrained('yachts')->nullOnDelete();
            $table->string('vessel')->nullable();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            // HTML из RichEditor: программа по дням.
            $table->longText('content')->nullable();

            $table->string('region')->nullable();
            // Одной строкой для карточки: «Сочи — Гагра — Новый Афон — Сочи».
            $table->string('route_summary')->nullable();

            // Дата начала обязательна: по ней тур делится на предстоящие и прошедшие.
            $table->date('date_start');
            $table->date('date_end')->nullable();

            // Цены — целые рубли (как adverts.price): это витринные величины,
            // копеек в них не бывает, а decimal возвращался бы строкой.
            $table->unsignedInteger('price_per_seat')->nullable();
            $table->unsignedInteger('price_per_cabin')->nullable();
            $table->unsignedInteger('org_fee')->nullable();
            // «+ судовая касса ~15 000 ₽ на человека» — у походов почти всегда есть.
            $table->text('price_note')->nullable();

            // Места ведёт админ вручную: бронирования и оплаты по ТЗ пока нет.
            $table->unsignedSmallInteger('seats_total')->nullable();
            $table->unsignedSmallInteger('seats_left')->nullable();

            // Ссылки на видео с подписями: [{url, caption}] — как у repair_cases.
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
        Schema::dropIfExists('tours');
    }
};
