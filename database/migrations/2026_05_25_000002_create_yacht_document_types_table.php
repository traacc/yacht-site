<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yacht_document_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique()->comment('Уникальный строковый ключ типа, например orc_certificate');
            $table->string('label')->comment('Читаемое название, например ORC-сертификат');
            $table->text('description')->nullable()->comment('Описание типа документа для подсказок');
            $table->boolean('is_configurable')->default(true)->comment('Можно ли настраивать обязательность');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yacht_document_types');
    }
};