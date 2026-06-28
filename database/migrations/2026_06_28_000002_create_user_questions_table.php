<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete(); // кто задал вопрос
            $table->text('question')->comment('Вопрос пользователя');
            $table->text('answer')->nullable()->comment('Ответ администрации (HTML)');
            $table->timestamp('answered_at')->nullable();
            $table->foreignUuid('answered_by')->nullable()->constrained('users')->nullOnDelete(); // кто ответил
            $table->boolean('imported_to_faq')->default(false)->comment('Перенесён ли в FAQ');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_questions');
    }
};
