<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug');
            $table->timestamps();
            $table->softDeletes();

            $table->index('published_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_category');
    }
};
