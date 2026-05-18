<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            // manual — ручная публикация, external — внешний источник (Этап 2)
            $table->enum('type', ['manual', 'external'])->default('manual');
            $table->string('title');
            $table->text('content');
            $table->string('external_url')->nullable();
            $table->string('cover_image_url')->nullable();
            // Флаг автоматической публикации в Telegram
            $table->boolean('published_to_tg')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('published_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
