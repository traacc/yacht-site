<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            // Владелец может выставить яхту в аренду. Конкретные регаты и
            // стоимость аренды хранятся в таблице yacht_rentals.
            $table->boolean('for_rent')->default(false)->after('current_mass_kg');
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropColumn('for_rent');
        });
    }
};
