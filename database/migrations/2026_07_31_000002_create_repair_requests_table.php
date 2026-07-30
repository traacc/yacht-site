<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Заявки по кнопке «Хотите такой ремонт?» (ТЗ 3-го этапа, раздел «Carter 30»).
 *
 * ТЗ требует письма в отдел заказов; храним заявки ещё и в БД, потому что
 * запрос, живущий только в почтовом ящике, теряется, а общее правило раздела —
 * «почта + админпанель + дашборд».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Кейс, с чьей страницы пришла заявка (null — с обзорной страницы раздела).
            $table->foreignUuid('repair_case_id')->nullable()->constrained('repair_cases')->nullOnDelete();

            // Авторизованный пользователь, если заявку оставили из-под аккаунта.
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('comment')->nullable();

            // Страница, с которой пришёл запрос.
            $table->string('source')->nullable();

            // Отметка обработки — по образцу user_questions.answered_at.
            $table->timestamp('processed_at')->nullable();
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_requests');
    }
};
