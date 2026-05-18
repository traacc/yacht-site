<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_url')->nullable();
            // Полиморфная привязка альбома к сущности (regatta, team, association)
            $table->string('albumable_type')->nullable();
            $table->uuid('albumable_id')->nullable();
            $table->timestamps();

            $table->index(['albumable_type', 'albumable_id']);
        });

        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('album_id')->constrained('albums')->cascadeOnDelete();
            $table->enum('type', ['photo', 'video']);
            $table->string('url');
            $table->string('thumbnail_url')->nullable();
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['album_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('albums');
    }
};
