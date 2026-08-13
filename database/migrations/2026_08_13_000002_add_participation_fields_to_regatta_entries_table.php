<?php

use App\Enums\ParticipationKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Заявка экипажем или индивидуально + добор людей в экипаж.
 *
 * `team_id` становится nullable: на регулярных и выездных регатах место
 * покупает отдельный человек, команды за ним нет. Уникальный ключ
 * (regatta_id, team_id) при этом сохраняется — MySQL допускает несколько
 * строк с NULL, так что индивидуальные заявки друг другу не мешают.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_entries', function (Blueprint $table) {
            $table->uuid('team_id')->nullable()->change();
        });

        Schema::table('regatta_entries', function (Blueprint $table) {
            $table->string('participation_kind', 20)
                ->default(ParticipationKind::Crew->value)
                ->after('yacht_id');

            // Автор заявки: ему уходят уведомления в ЛК — об одобрении админом
            // и о желающих попасть в экипаж.
            $table->foreignUuid('user_id')->nullable()->after('participation_kind')
                ->constrained('users')->nullOnDelete();

            // Клубные регаты: экипаж открывает добор людей со стороны и указывает
            // условия и почту, на которую уходят отклики.
            $table->boolean('open_for_join')->default(false)->after('fee_paid');
            $table->text('join_conditions')->nullable()->after('open_for_join');
            $table->string('join_contact_email')->nullable()->after('join_conditions');
        });
    }

    public function down(): void
    {
        Schema::table('regatta_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['participation_kind', 'open_for_join', 'join_conditions', 'join_contact_email']);
        });

        Schema::table('regatta_entries', function (Blueprint $table) {
            $table->uuid('team_id')->nullable(false)->change();
        });
    }
};
