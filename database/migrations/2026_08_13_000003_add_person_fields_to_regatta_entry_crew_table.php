<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Участник экипажа без команды.
 *
 * До сих пор строка экипажа обязательно ссылалась на `team_members` — то есть
 * человек попадал в экипаж только через команду. На регулярных и выездных
 * регатах экипаж сборный, поэтому `team_member_id` становится nullable, а
 * человек описывается либо ссылкой на пользователя, либо контактами (гость).
 *
 * `user_id` важен ещё и для личного рейтинга: очки начисляются участникам
 * экипажа, и без него сборный экипаж остался бы без зачёта
 * (@see App\Services\RatingCalculator::crewByRegattaEntry()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_entry_crew', function (Blueprint $table) {
            $table->uuid('team_member_id')->nullable()->change();
        });

        Schema::table('regatta_entry_crew', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->after('team_member_id')
                ->constrained('users')->nullOnDelete();
            $table->string('full_name')->nullable()->after('user_id');
            $table->string('email')->nullable()->after('full_name');
            $table->string('phone')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('regatta_entry_crew', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['full_name', 'email', 'phone']);
        });

        Schema::table('regatta_entry_crew', function (Blueprint $table) {
            $table->uuid('team_member_id')->nullable(false)->change();
        });
    }
};
