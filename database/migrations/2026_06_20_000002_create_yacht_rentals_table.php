<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yacht_rentals', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Арендуемая яхта
            $table->foreignUuid('yacht_id')->constrained('yachts')->cascadeOnDelete();

            // Регата, на которую яхта доступна в аренду
            $table->foreignUuid('regatta_id')->constrained('regattas')->cascadeOnDelete();

            // Стоимость аренды на данную регату
            $table->decimal('price', 12, 2)->nullable();

            $table->timestamps();

            // Одна яхта — одна цена на конкретную регату
            $table->unique(['yacht_id', 'regatta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yacht_rentals');
    }
};
