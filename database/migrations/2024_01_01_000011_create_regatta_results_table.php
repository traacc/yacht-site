<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regatta_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('regatta_id')->constrained('regattas')->cascadeOnDelete();
            $table->enum('result_type', ['preliminary', 'final'])->default('preliminary');
            $table->enum('source', ['manual', 'imported'])->default('manual');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regatta_results');
    }
};
