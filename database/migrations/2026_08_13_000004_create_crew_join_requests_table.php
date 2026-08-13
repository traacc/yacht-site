<?php

use App\Enums\CrewJoinRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Отклик «Хочу в этот экипаж» на клубной регате.
 *
 * Отдельная таблица, а не строка экипажа со статусом: откликнуться может любой
 * человек, в том числе без аккаунта, и до решения капитана он ещё не участник.
 * Принятый отклик превращается в строку `regatta_entry_crew`
 * (@see App\Actions\RegattaEntry\ResolveCrewJoinRequestAction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_join_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('regatta_entry_id')->constrained('regatta_entries')->cascadeOnDelete();

            // Гость остаётся без user_id — контакты берутся из полей ниже.
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('message')->nullable();

            $table->string('status', 20)->default(CrewJoinRequestStatus::Pending->value)->index();
            $table->text('response_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_join_requests');
    }
};
