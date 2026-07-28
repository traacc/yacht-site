<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_registries', function (Blueprint $table) {
            // Назначение платежа (App\Enums\PaymentPurpose).
            // Nullable: у платежей, созданных до справочника, назначение неизвестно.
            $table->string('purpose', 32)->nullable()->after('amount');

            // Снимок ФИО плательщика. Именно снимок, а не FK: наличные может
            // вносить человек, которого нет среди пользователей сайта.
            $table->string('payer_name')->nullable()->after('purpose');

            // Денормализованные связи для группировки и фильтров: заполняются
            // из payable (заявка/команда), см. SyncPaymentRegistryLinksAction.
            // nullOnDelete — платёж переживает удаление регаты/яхты/команды.
            $table->foreignUuid('regatta_id')->nullable()->after('payable_id')
                ->constrained('regattas')->nullOnDelete();
            $table->foreignUuid('yacht_id')->nullable()->after('regatta_id')
                ->constrained('yachts')->nullOnDelete();
            $table->foreignUuid('team_id')->nullable()->after('yacht_id')
                ->constrained('teams')->nullOnDelete();

            // Сценарий из ТЗ: «по такой-то яхте на такой регате в такую-то дату».
            $table->index(['regatta_id', 'yacht_id', 'paid_at'], 'payment_registries_regatta_yacht_paid_index');
            // «Стартовые взносы за период».
            $table->index(['purpose', 'paid_at'], 'payment_registries_purpose_paid_index');
            $table->index('paid_at', 'payment_registries_paid_at_index');
            $table->index('payer_name', 'payment_registries_payer_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('payment_registries', function (Blueprint $table) {
            $table->dropIndex('payment_registries_regatta_yacht_paid_index');
            $table->dropIndex('payment_registries_purpose_paid_index');
            $table->dropIndex('payment_registries_paid_at_index');
            $table->dropIndex('payment_registries_payer_name_index');

            $table->dropConstrainedForeignId('regatta_id');
            $table->dropConstrainedForeignId('yacht_id');
            $table->dropConstrainedForeignId('team_id');

            $table->dropColumn(['purpose', 'payer_name']);
        });
    }
};
