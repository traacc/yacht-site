<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Заявка приходит от неавторизованного посетителя: пользователя
            // с таким email может и не быть, поэтому связь необязательная.
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->index()->comment('E-mail из формы восстановления');
            $table->string('phone')->comment('Телефон из формы восстановления');
            $table->text('answer')->nullable()->comment('Ответ администрации (текст письма)');
            $table->timestamp('answered_at')->nullable();
            $table->foreignUuid('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reset_link_sent_at')->nullable()->comment('Когда из панели отправили ссылку на смену пароля');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_requests');
    }
};
