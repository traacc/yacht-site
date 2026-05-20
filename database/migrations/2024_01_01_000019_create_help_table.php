<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('help_category_id')->nullable()->constrained('help_category')->nullOnDelete();
            $table->string('title');
            $table->string('desc')->nullable();
            $table->json('includes')->nullable();

            $table->enum('contact_type', ['specialist', 'manager'])->default('specialist');

            $table->string('specialist_name')->nullable();
            $table->string('specialist_email')->nullable();
            $table->string('specialist_phone')->nullable();
            $table->string('specialist_sphere')->nullable();
            $table->string('specialist_city')->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
