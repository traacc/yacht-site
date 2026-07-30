<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Справочник категорий объявлений.
 *
 * Категории свои у каждой доски (`type`), поэтому slug уникален в паре с ним:
 * «Паруса» в Барахолке и «Паруса» в будущей бирже парусов — разные записи.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advert_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Значения App\Enums\AdvertType. Строкой, а не ENUM: новая доска
            // не должна требовать миграции (см. AGENTS.md о ENUM-колонках).
            $table->string('type', 32);

            $table->string('title');
            $table->string('slug');
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['type', 'slug']);
            $table->index(['type', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advert_categories');
    }
};
