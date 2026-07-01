<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yacht_option_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('yacht_option_id')->constrained('yacht_options')->cascadeOnDelete();
            $table->string('key')->comment('Ключ значения в рамках опции, например dacron');
            $table->string('label')->comment('Читаемое название значения, например Дакрон');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['yacht_option_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yacht_option_values');
    }
};
