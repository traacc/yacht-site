<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Кейсы подраздела «Ремонт и модернизация» раздела «Carter 30».
 *
 * По ТЗ 3-го этапа это «подразделы с конкретными кейсами (как ссылки с
 * названиями проектов или конкретных яхт)»: у каждого своя страница с текстом,
 * чертежами, фото и видео.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Кейс может быть привязан к конкретной яхте, а может описывать проект целиком.
            $table->foreignUuid('yacht_id')->nullable()->constrained('yachts')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();

            // Тизер для списка кейсов на обзорной странице.
            $table->text('summary')->nullable();

            // HTML из RichEditor: текст с картинками и чертежами в теле.
            $table->longText('content')->nullable();

            // Ссылки на видео с подписями: [{url, caption}].
            // Своей таблицы не заводим — набор маленький и правится тем же
            // репитером, что и остальной контент кейса.
            $table->json('video_links')->nullable();

            $table->boolean('is_published')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_cases');
    }
};
