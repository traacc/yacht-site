<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Чартерные яхты зарубежной регаты (ТЗ 3-го этапа, п. 7).
 *
 * Это не яхты реестра (`yachts`): лодка берётся в чартер за границей, владельца
 * и документов на сайте у неё нет — по ТЗ нужны только модель, название, год,
 * цена аренды и текущая занятость.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foreign_regatta_yachts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('foreign_regatta_id')
                ->constrained('foreign_regattas')
                ->cascadeOnDelete();

            $table->string('model');
            $table->string('name')->nullable();
            $table->unsignedSmallInteger('year')->nullable();

            $table->unsignedInteger('price')->nullable();
            // «за неделю», «за регату» — подпись к цене, значения App\Enums\CharterPriceUnit.
            $table->string('price_unit', 16)->nullable();
            $table->string('price_note')->nullable();

            // Значения App\Enums\CharterYachtStatus — строкой, чтобы новый
            // статус не требовал ENUM-миграции (как `service_requests.type`).
            $table->string('status', 32)->default('free');

            $table->integer('sort_order')->default(0);

            $table->timestamps();
            // Мягкое удаление: подпись выбранной яхты в уже поданной заявке
            // резолвится по этой таблице и не должна пропадать.
            $table->softDeletes();

            $table->index(['foreign_regatta_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foreign_regatta_yachts');
    }
};
