<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Пункты расписания уже перенесены в regatta_schedule_events —
        // удаляем их из regatta_events, оставляя только гонки.
        DB::table('regatta_events')->where('event_type', 'schedule')->delete();

        Schema::table('regatta_events', function (Blueprint $table) {
            $table->dropColumn('event_type');
        });
    }

    public function down(): void
    {
        Schema::table('regatta_events', function (Blueprint $table) {
            $table->enum('event_type', ['schedule', 'race'])->default('race');
        });

        // Возвращаем перенесённые пункты расписания обратно в regatta_events.
        if (Schema::hasTable('regatta_schedule_events')) {
            DB::table('regatta_schedule_events')->orderBy('event_datetime')->each(function ($event) {
                DB::table('regatta_events')->insert([
                    'id'             => $event->id,
                    'regatta_id'     => $event->regatta_id,
                    'name'           => $event->name,
                    'description'    => $event->description,
                    'event_datetime' => $event->event_datetime,
                    'event_type'     => 'schedule',
                    'created_at'     => $event->created_at,
                    'updated_at'     => $event->updated_at,
                ]);
            });
        }
    }
};
