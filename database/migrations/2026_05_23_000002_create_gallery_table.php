<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Создаёт таблицу gallery.
     *
     * ИЗМЕНЕНИЯ относительно исходной миграции:
     *   – Удалена колонка cover_path (string) — обложка теперь хранится в таблице media
     *     Spatie Media Library, коллекция 'cover'.
     *   – Удалена колонка images (json) — изображения галереи теперь хранятся в таблице media
     *     Spatie Media Library, коллекция 'images'.
     *
     * Таблица media уже создана отдельной миграцией (2026_05_25_190849) и адаптирована
     * под UUID-модели (2026_05_25_194652).
     */
    public function up(): void
    {
        Schema::create('gallery', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('season_id')->nullable()->constrained('seasons')->nullOnDelete();
            $table->foreignUuid('regatta_id')->nullable()->constrained('regattas')->nullOnDelete();
            $table->string('name');
            $table->string('water_area')->nullable();
            $table->date('date')->nullable();
            // ↓↓↓ УДАЛЕНО: $table->string('cover_path')->nullable();
            // ↓↓↓ УДАЛЕНО: $table->json('images')->nullable();
            // Медиафайлы (обложка, фотографии) хранятся через Spatie Media Library в таблице media.
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery');
    }
};
