<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Запись бухгалтерского реестра, которую оплачивает транзакция.
            $table->foreignUuid('payment_registry_id')->constrained('payment_registries')->cascadeOnDelete();

            // Плательщик (инициатор онлайн-оплаты). Nullable — пользователь может быть удалён.
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Код провайдера эквайринга (App\Enums\PaymentProviderCode).
            $table->string('provider');

            // Идентификатор платежа на стороне провайдера. Nullable — до ответа провайдера.
            $table->string('external_id')->nullable();

            // Статус транзакции (App\Enums\PaymentTransactionStatus).
            $table->string('status')->default('pending');

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('RUB');
            $table->string('description', 500);

            // URL страницы оплаты у провайдера, куда редиректим плательщика.
            $table->text('confirmation_url')->nullable();

            // Ключ идемпотентности, генерируется до обращения к провайдеру.
            $table->uuid('idempotence_key')->unique();

            // Последний raw-ответ провайдера или payload вебхука (для разбора инцидентов).
            $table->json('payload')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamps();

            // Поиск транзакции по вебхуку; NULL-значения external_id в MySQL не конфликтуют.
            $table->unique(['provider', 'external_id'], 'payment_transactions_provider_external_unique');

            // Выборка «зависших» pending-транзакций командой payments:reconcile.
            $table->index(['status', 'created_at'], 'payment_transactions_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
