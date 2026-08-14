<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Лодка зарубежной регаты: дивизион, полная спецификация и продажа мест.
 *
 * Спецификация продублирована с дивизионом намеренно: у флота одинаковых лодок
 * она заполняется на дивизионе и наследуется, у списка конкретных лодок —
 * заполняется здесь. Пустое поле означает «взять у дивизиона»
 * (@see App\Models\ForeignRegattaYacht::spec()), поэтому `model` перестаёт быть
 * обязательной — у лодки из флота своей модели нет.
 *
 * Шкипер и свободные места решают, что предлагается на витрине: есть шкипер —
 * лодка набирает экипаж и продаёт места, нет шкипера — сдаётся целиком.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foreign_regatta_yachts', function (Blueprint $table) {
            $table->foreignUuid('division_id')
                ->nullable()
                ->after('foreign_regatta_id')
                ->constrained('foreign_regatta_divisions')
                ->nullOnDelete();

            $table->text('description')->nullable()->after('year');
            $table->unsignedTinyInteger('cabins')->nullable()->after('description');
            // Значения App\Enums\DownwindSail.
            $table->string('downwind_sail', 16)->nullable()->after('cabins');

            $table->unsignedInteger('charter_fee')->nullable()->after('price_unit');
            $table->unsignedInteger('deposit')->nullable()->after('charter_fee');

            $table->string('skipper_name')->nullable()->after('price_note');
            $table->string('skipper_note')->nullable()->after('skipper_name');
            $table->unsignedTinyInteger('free_seats')->nullable()->after('skipper_note');
            $table->unsignedInteger('seat_price')->nullable()->after('free_seats');
            $table->string('seat_note')->nullable()->after('seat_price');

            $table->index(['division_id', 'sort_order']);
        });

        // Лодка из флота своей модели не имеет — берёт её у дивизиона.
        Schema::table('foreign_regatta_yachts', function (Blueprint $table) {
            $table->string('model')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('foreign_regatta_yachts', function (Blueprint $table) {
            $table->dropIndex(['division_id', 'sort_order']);
            $table->dropConstrainedForeignId('division_id');

            $table->dropColumn([
                'description',
                'cabins',
                'downwind_sail',
                'charter_fee',
                'deposit',
                'skipper_name',
                'skipper_note',
                'free_seats',
                'seat_price',
                'seat_note',
            ]);
        });
    }
};
