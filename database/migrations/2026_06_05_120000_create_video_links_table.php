<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Создаёт таблицу video_links — ссылки на видео для галереи.
     *
     * В отличие от коллекции 'videos' Spatie Media Library (которая хранит
     * загруженные видеофайлы), эта таблица предназначена для внешних ссылок
     * (YouTube, Vimeo, Rutube и т.д.).
     */
    public function up(): void
    {
        Schema::create('video_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('gallery_id')->constrained('gallery')->cascadeOnDelete();
            $table->string('url');
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_links');
    }
};
