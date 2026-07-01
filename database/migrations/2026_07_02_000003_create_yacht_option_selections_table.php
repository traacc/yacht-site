<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yacht_option_selections', function (Blueprint $table) {
            $table->foreignUuid('yacht_id')->constrained('yachts')->cascadeOnDelete();
            $table->foreignUuid('yacht_option_id')->constrained('yacht_options')->cascadeOnDelete();
            $table->foreignUuid('yacht_option_value_id')->constrained('yacht_option_values')->cascadeOnDelete();

            // Одна яхта может выбрать только одно значение для каждой опции.
            $table->primary(['yacht_id', 'yacht_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yacht_option_selections');
    }
};
