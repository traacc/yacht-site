<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Пресса о нас» (ТЗ 3-го этапа, п. 9) — публикации сторонних изданий
 * об ассоциации и соревнованиях Carter 30.
 *
 * От новости отличается происхождением: материал написан не нами, поэтому
 * обязателен `source_url` — ссылка на оригинал, а `content` хранит перепечатку
 * текста (заказчик хочет и ссылку, и сам текст на сайте).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_mentions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('title');
            // Slug — ключ маршрута страницы публикации (/press/{slug}).
            $table->string('slug')->unique();
            // Издание: «Ведомости», «Yacht Russia» и т.п.
            $table->string('source_name');
            // Ссылка на оригинал. Обязательна: без неё это уже не «пресса о нас».
            $table->string('source_url', 2048);
            // Дата выхода публикации в издании, а не даты записи в админке.
            $table->date('published_at')->nullable();

            $table->text('summary')->nullable();
            // HTML из RichEditor: перепечатка текста статьи.
            $table->longText('content')->nullable();

            $table->boolean('is_published')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_mentions');
    }
};
