<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('season_id')->nullable()->constrained('seasons')->nullOnDelete();
            $table->foreignUuid('regatta_id')->nullable()->constrained('regattas')->nullOnDelete();
            $table->string('name');
            $table->string('water_area')->nullable();
            $table->date('date')->nullable();
            $table->string('cover_path')->nullable();
            // JSON-массив путей к файлам галереи
            $table->json('images')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery');
    }
};
