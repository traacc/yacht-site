<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Заявки раздела «Услуги» (ТЗ 3-го этапа, п. 7).
 *
 * Одна таблица на все подразделы: `type` различает их, а поля, специфичные для
 * подраздела, лежат в `payload` — наборы у семи подтипов разные, и индекс по
 * ним всё равно не нужен. В отдельные колонки вынесено только то, по чему
 * админка ищет, фильтрует и сортирует.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Значения App\Enums\ServiceType / ServiceRequestStatus — строками,
            // чтобы новый подраздел и новый статус не требовали ENUM-миграции.
            $table->string('type', 32);
            $table->string('status', 32)->default('new');

            // Заявку может оставить и гость: контакты обязательны, аккаунт — нет.
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Объект заявки: яхта, тур, зарубежная регата, сертификат. Морф —
            // потому что моделей будущих подразделов ещё нет.
            $table->nullableUuidMorphs('subject');

            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('comment')->nullable();

            // Диапазон и количество есть почти у всех подтипов, по ним сортируют.
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->unsignedSmallInteger('quantity')->nullable();

            // Специфичные поля подтипа — см. ServiceType::payloadFields().
            $table->json('payload')->nullable();

            // Задел под оплату услуг: связь заводим сейчас, эквайринг ещё не
            // выбран (ТЗ п. 4.1), поэтому логики оплаты здесь нет.
            $table->foreignUuid('payment_registry_id')->nullable()
                ->constrained('payment_registries')->nullOnDelete();

            $table->string('source')->nullable();
            $table->text('admin_comment')->nullable();

            $table->timestamp('processed_at')->nullable();
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['type', 'status', 'created_at']);
            $table->index('status');
            $table->index(['date_start', 'date_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
