<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regatta_schedule_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('regatta_id')->constrained('regattas')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->dateTime('event_datetime')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['regatta_id', 'sort_order']);
        });

        // Переносим существующие пункты расписания (event_type = 'schedule')
        // из regatta_events в новую таблицу.
        DB::table('regatta_events')
            ->where('event_type', 'schedule')
            ->orderBy('event_datetime')
            ->each(function ($event) {
                DB::table('regatta_schedule_events')->insert([
                    'id'             => $event->id,
                    'regatta_id'     => $event->regatta_id,
                    'name'           => $event->name,
                    'description'    => $event->description,
                    'event_datetime' => $event->event_datetime,
                    'sort_order'     => 0,
                    'created_at'     => $event->created_at,
                    'updated_at'     => $event->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('regatta_schedule_events');
    }
};
