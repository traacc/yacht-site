<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Разделить предложения биржи парусов на продажу и аренду.
     *
     * Раньше вид был один — «Продам или сдам», а аренду выдавала только
     * единица цены «₽/день». По ней и раскладываем: посуточная цена — аренда,
     * всё остальное — продажа (у продажи залога нет, его стираем).
     */
    public function up(): void
    {
        DB::table('adverts')
            ->where('type', 'sails')
            ->where('kind', 'offer')
            ->where('price_unit', 'per_day')
            ->update(['kind' => 'rent']);

        DB::table('adverts')
            ->where('type', 'sails')
            ->where('kind', 'offer')
            ->update(['kind' => 'sale', 'price_unit' => 'total', 'deposit' => null]);
    }

    public function down(): void
    {
        DB::table('adverts')
            ->where('type', 'sails')
            ->whereIn('kind', ['sale', 'rent'])
            ->update(['kind' => 'offer']);
    }
};
