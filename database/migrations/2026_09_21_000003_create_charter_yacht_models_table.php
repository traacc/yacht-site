<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Справочник моделей чартерных яхт.
 *
 * Одна и та же лодка ходит из регаты в регату, а описание, галерея и
 * характеристики у неё не меняются — раньше их приходилось заводить заново для
 * каждой регаты (`foreign_regatta_yachts` живёт в рамках одной, с каскадным
 * удалением). Справочник хранит именно модель: описание, фотографии, каюты,
 * парус и страну базирования.
 *
 * Год выпуска и название здесь не хранятся: у монотипного флота они у каждой
 * лодки свои — это свойство экземпляра, а не модели.
 *
 * Цен в справочнике нет намеренно: цена зависит от регаты и сезона и остаётся
 * у дивизиона и у лодки дивизиона.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charter_yacht_models', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // «Bavaria 46», «First 40CR» — то, что на витрине показывается моделью.
            $table->string('name');

            // Страна базирования: проставляется от страны регаты, где модель
            // завели впервые (@see App\Actions\Service\AssignCharterYachtModelCountry).
            $table->string('country')->nullable();

            $table->unsignedTinyInteger('cabins')->nullable();
            // Значения App\Enums\DownwindSail — строкой, как у лодок и дивизионов.
            $table->string('downwind_sail', 16)->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
            // Мягкое удаление: модель, на которую ссылается заведённый флот,
            // не должна исчезать из уже опубликованных регат.
            $table->softDeletes();

            $table->index(['country', 'name']);
        });

        // Монотипный дивизион ссылается на модель целиком: по ТЗ в нём
        // «вводятся только модели яхт с описанием и галереей».
        Schema::table('foreign_regatta_divisions', function (Blueprint $table): void {
            $table->foreignUuid('yacht_model_id')
                ->nullable()
                ->after('name')
                ->constrained('charter_yacht_models')
                ->nullOnDelete();
        });

        // Лодка дивизиона-списка ссылается на свою модель; название и год
        // остаются её собственными.
        Schema::table('foreign_regatta_yachts', function (Blueprint $table): void {
            $table->foreignUuid('yacht_model_id')
                ->nullable()
                ->after('division_id')
                ->constrained('charter_yacht_models')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('foreign_regatta_yachts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('yacht_model_id');
        });

        Schema::table('foreign_regatta_divisions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('yacht_model_id');
        });

        Schema::dropIfExists('charter_yacht_models');
    }
};
