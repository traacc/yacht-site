<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_result_items', function (Blueprint $table) {
            // Признак ручного ввода: если true — авторасчёт это поле не перезаписывает.
            $table->boolean('total_points_overridden')->default(false)->after('total_points');
            $table->boolean('final_position_overridden')->default(false)->after('final_position');
        });
    }

    public function down(): void
    {
        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->dropColumn(['total_points_overridden', 'final_position_overridden']);
        });
    }
};
