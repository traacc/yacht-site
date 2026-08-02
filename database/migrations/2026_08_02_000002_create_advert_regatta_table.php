<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Регаты, на которые подано объявление.
 *
 * Нужно доске «Яхты для соревнований» (ТЗ п. 8.2: «выбор нескольких регат»);
 * связь общая, чтобы подключить её к другой бирже можно было одним
 * AdvertType::usesRegattas().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advert_regatta', function (Blueprint $table): void {
            $table->foreignUuid('advert_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('regatta_id')->constrained()->cascadeOnDelete();

            $table->primary(['advert_id', 'regatta_id']);
            $table->index('regatta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advert_regatta');
    }
};
