<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yacht_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique()->comment('Уникальный строковый ключ опции, например gps_navigator');
            $table->string('label')->comment('Читаемое название, например GPS-навигатор');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yacht_options');
    }
};
