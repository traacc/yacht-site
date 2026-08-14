<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Дивизионы флота зарубежной регаты.
 *
 * Флот объявляется двумя способами, и оба сводятся к дивизиону:
 *  - `fleet` — N одинаковых лодок: характеристики живут здесь, а сами лодки
 *    заводятся автоматически по `yachts_count` (@see App\Actions\Service\SyncFleetDivisionYachts);
 *  - `list`  — список разных лодок: дивизион только группирует и именует,
 *    характеристики у каждой лодки свои.
 *
 * Строки в `foreign_regatta_yachts` нужны в обоих случаях: шкипер и свободные
 * места — свойство конкретной лодки, а не дивизиона.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foreign_regatta_divisions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('foreign_regatta_id')
                ->constrained('foreign_regattas')
                ->cascadeOnDelete();

            // Значения App\Enums\FleetDivisionType — строкой, чтобы новый тип
            // не требовал ENUM-миграции (как `foreign_regatta_yachts.status`).
            $table->string('type', 16)->default('fleet');
            $table->string('name')->nullable();

            // Спецификация дивизиона-флота: одна на все его лодки. Для типа
            // `list` не заполняется — там характеристики у каждой лодки свои.
            $table->string('model')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('cabins')->nullable();
            // Значения App\Enums\DownwindSail: спинакер, геннакер, оба, нет.
            $table->string('downwind_sail', 16)->nullable();

            // Цены — целые рубли, как у самой регаты и у туров.
            $table->unsignedInteger('price')->nullable();
            // Значения App\Enums\CharterPriceUnit: за регату, за неделю, в сутки.
            $table->string('price_unit', 16)->nullable();
            $table->unsignedInteger('charter_fee')->nullable();
            $table->unsignedInteger('deposit')->nullable();
            $table->string('price_note')->nullable();

            // Сколько лодок в дивизионе: столько строк яхт и будет заведено.
            $table->unsignedSmallInteger('yachts_count')->nullable();

            $table->integer('sort_order')->default(0);

            $table->timestamps();
            // Мягкое удаление: подпись выбранной яхты в уже поданной заявке
            // резолвится через дивизион и не должна пропадать.
            $table->softDeletes();

            $table->index(['foreign_regatta_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foreign_regatta_divisions');
    }
};
