<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_registries', function (Blueprint $table) {
            // Отметка бухгалтера о фактическом приходе средств.
            // Null — приход ещё не сверен (не путать со статусом оплаты).
            $table->timestamp('confirmed_at')->nullable()->after('paid_at');

            // Кто подтвердил приход. Nullable: пользователя могут удалить.
            $table->foreignUuid('confirmed_by')->nullable()->after('confirmed_at')
                ->constrained('users')->nullOnDelete();

            // Кто последний изменил запись. Null — изменение выполнила система
            // (вебхук эквайринга, консольная команда, публичная форма заявки).
            $table->foreignUuid('updated_by')->nullable()->after('confirmed_by')
                ->constrained('users')->nullOnDelete();

            // Финансовые записи не удаляем физически — история должна сохраняться.
            $table->softDeletes();

            $table->index('confirmed_at', 'payment_registries_confirmed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('payment_registries', function (Blueprint $table) {
            $table->dropIndex('payment_registries_confirmed_at_index');
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('confirmed_at');
            $table->dropSoftDeletes();
        });
    }
};
