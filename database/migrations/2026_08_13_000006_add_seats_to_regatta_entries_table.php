<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сколько мест куплено индивидуальной заявкой.
 *
 * Человек берёт места и на спутников, чьи имена ещё не известны, поэтому
 * считать их по строкам экипажа нельзя: в экипаже он пока один, а счёт нужно
 * выставить на все места (@see App\Actions\Regatta\SubmitSeatEntryAction).
 * Для заявок экипажем поле остаётся равным 1 и не используется.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_entries', function (Blueprint $table) {
            $table->unsignedTinyInteger('seats')->default(1)->after('participation_kind');
        });
    }

    public function down(): void
    {
        Schema::table('regatta_entries', function (Blueprint $table) {
            $table->dropColumn('seats');
        });
    }
};
