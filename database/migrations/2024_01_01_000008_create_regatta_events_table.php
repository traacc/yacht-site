<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regatta_events', function (Blueprint $table) {
            $table->string('name');
            $table->string('description')->nullable();
            $table->uuid('id')->primary();
            $table->foreignUuid('regatta_id')->constrained('regattas')->cascadeOnDelete();
            $table->dateTime('event_datetime')->nullable();
            $table->enum('event_type', ['schedule', 'race'])
                  ->default('schedule');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regatta_events');
    }
};
