<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Объявления с премодерацией (ТЗ 3-го этапа, п. 5: «Барахолка», «Продать яхту»).
 *
 * Одна таблица на все доски: тип различает их (`type`), а поля, нужные не
 * каждой доске, оставлены nullable. Четыре биржи раздела «Соревнования» (п. 8)
 * добавят к этой же таблице свои колонки отдельной миграцией.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adverts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Значения App\Enums\AdvertType / App\Enums\AdvertStatus — строками,
            // чтобы новая доска и новый статус не требовали миграции.
            $table->string('type', 32);
            $table->string('status', 32);

            // Автор. Объявления только для зарегистрированных: премодерация,
            // уведомление о решении и переписка с автором без аккаунта не работают.
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // Категория — только у Барахолки, яхта — только у «Продать яхту».
            $table->foreignUuid('advert_category_id')->nullable()->constrained('advert_categories')->nullOnDelete();
            $table->foreignUuid('yacht_id')->nullable()->constrained('yachts')->nullOnDelete();

            $table->string('title');
            $table->text('description');

            // Цена в целых рублях; при «договорной» сумма может отсутствовать.
            $table->unsignedBigInteger('price')->nullable();
            $table->boolean('price_negotiable')->default(false);

            $table->string('city')->nullable();

            // «Контакты для публикации» по формулировке ТЗ — все необязательны.
            $table->string('contact_phone')->nullable();
            $table->string('contact_telegram')->nullable();
            $table->string('contact_email')->nullable();

            // Причина отказа показывается автору в личном кабинете.
            $table->text('rejection_reason')->nullable();

            $table->timestamp('moderated_at')->nullable();
            $table->foreignUuid('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status', 'published_at']);
            $table->index('user_id');
            $table->index('advert_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adverts');
    }
};
