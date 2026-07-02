<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yacht_rental_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Яхта, которую хотят арендовать
            $table->foreignUuid('yacht_id')->constrained('yachts')->cascadeOnDelete();

            // Контактные данные заявителя
            $table->string('name');
            $table->string('phone');

            // Желаемая дата аренды и комментарий
            $table->date('desired_date')->nullable();
            $table->text('comment')->nullable();

            // Откуда пришёл запрос и авторизованный пользователь (если есть)
            $table->string('source')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('yacht_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yacht_rental_requests');
    }
};
