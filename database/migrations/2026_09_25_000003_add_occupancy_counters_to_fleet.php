<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Занятость по каждому типу мест: сколько всего и сколько занято.
 *
 * Раньше у лодки было одно число «свободных мест» на все варианты сразу: нельзя
 * было сказать «каюты разобрали, а отдельные места есть», не было знаменателя
 * для «занято 4 из 5» и не было самого состояния «занято» — вариант просто
 * тихо исчезал с витрины.
 *
 * Счётчики парные и заводятся на обоих уровнях. У монотипа без выбора яхт они
 * ведутся на дивизионе: лодки там одинаковые, места продаются общим пулом. У
 * монотипа с выбором и у гандикапа — у каждой лодки своей строкой.
 *
 * Появляется и четвёртый тип места — одноместная каюта
 * (@see App\Enums\ParticipationOption), поэтому рядом со счётчиками встаёт его
 * цена.
 */
return new class extends Migration
{
    /** Счётчики, общие для дивизиона и лодки. */
    private const COUNTERS = [
        'seats_total',
        'seats_taken',
        'solo_seats_total',
        'solo_seats_taken',
        'cabins_total',
        'cabins_taken',
    ];

    public function up(): void
    {
        Schema::table('foreign_regatta_divisions', function (Blueprint $table): void {
            $table->unsignedInteger('solo_seat_price')->nullable()->after('seat_price');

            foreach (self::COUNTERS as $column) {
                $table->unsignedSmallInteger($column)->nullable()->after('yachts_count');
            }

            // «Яхта целиком» у монотипа без выбора тоже считается: всего лодок
            // столько, сколько их в дивизионе, — отдельного «всего» не нужно.
            $table->unsignedSmallInteger('yachts_taken')->nullable()->after('yachts_count');
        });

        Schema::table('foreign_regatta_yachts', function (Blueprint $table): void {
            $table->unsignedInteger('solo_seat_price')->nullable()->after('seat_price');

            foreach (self::COUNTERS as $column) {
                $table->unsignedSmallInteger($column)->nullable()->after('free_seats');
            }
        });

        // «Свободно N мест» переезжает в «всего N, занято 0»: сколько мест уже
        // продано, старая схема не знала.
        DB::table('foreign_regatta_yachts')
            ->whereNotNull('free_seats')
            ->update([
                'seats_total' => DB::raw('free_seats'),
                'seats_taken' => 0,
            ]);

        Schema::table('foreign_regatta_yachts', function (Blueprint $table): void {
            $table->dropColumn('free_seats');
        });
    }

    public function down(): void
    {
        Schema::table('foreign_regatta_yachts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('free_seats')->nullable()->after('skipper_note');
        });

        DB::table('foreign_regatta_yachts')
            ->whereNotNull('seats_total')
            ->update(['free_seats' => DB::raw('GREATEST(seats_total - COALESCE(seats_taken, 0), 0)')]);

        Schema::table('foreign_regatta_yachts', function (Blueprint $table): void {
            $table->dropColumn([...self::COUNTERS, 'solo_seat_price']);
        });

        Schema::table('foreign_regatta_divisions', function (Blueprint $table): void {
            $table->dropColumn([...self::COUNTERS, 'solo_seat_price', 'yachts_taken']);
        });
    }
};
