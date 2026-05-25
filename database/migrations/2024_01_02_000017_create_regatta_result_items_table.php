<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regatta_result_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('regatta_result_id')->constrained('regatta_results')->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignUuid('yacht_id')->nullable()->constrained('yachts')->restrictOnDelete();
            $table->decimal('total_points', 10, 3)->default(0);
            $table->unsignedSmallInteger('final_position')->nullable();
            $table->timestamps();

            $table->unique(['regatta_result_id', 'team_id']);
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regatta_result_items');
    }
};
