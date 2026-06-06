<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Конвертируем существующие строки в JSON-массив
        DB::table('help')
            ->whereNotNull('specialist_phone')
            ->where('specialist_phone', '!=', '')
            ->get(['id', 'specialist_phone'])
            ->each(function ($row) {
                $decoded = json_decode($row->specialist_phone, true);
                if (! is_array($decoded)) {
                    DB::table('help')->where('id', $row->id)->update([
                        'specialist_phone' => json_encode([$row->specialist_phone]),
                    ]);
                }
            });

        Schema::table('help', function (Blueprint $table) {
            $table->json('specialist_phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('help', function (Blueprint $table) {
            $table->string('specialist_phone')->nullable()->change();
        });
    }
};
