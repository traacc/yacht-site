<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Модель дивизиона — для монотипа с выбором яхт.
 *
 * Такой дивизион заполняется как гандикапный: цены, места, шкипер и занятость
 * у каждой лодки свои. Отличается он ровно одним — лодки в нём одной модели,
 * поэтому модель выбирается из справочника один раз на дивизион, а не у каждой
 * лодки (@see App\Enums\FleetDivisionType::MonotypeChoice).
 *
 * Монотип без выбора яхт колонкой не пользуется: там модель вводится строкой.
 *
 * Если такие дивизионы уже заведены, модель поднимается к ним от первой лодки,
 * а сами лодки ссылку теряют — иначе у них осталась бы «своя» модель, которой
 * по новым правилам быть не может.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foreign_regatta_divisions', function (Blueprint $table): void {
            $table->foreignUuid('yacht_model_id')
                ->nullable()
                ->after('name')
                ->constrained('charter_yacht_models')
                ->nullOnDelete();
        });

        DB::table('foreign_regatta_divisions')
            ->where('type', 'monotype_choice')
            ->get()
            ->each(function (object $division): void {
                $model = DB::table('foreign_regatta_yachts')
                    ->where('division_id', $division->id)
                    ->whereNotNull('yacht_model_id')
                    ->orderBy('sort_order')
                    ->value('yacht_model_id');

                if ($model === null) {
                    return;
                }

                DB::table('foreign_regatta_divisions')
                    ->where('id', $division->id)
                    ->update(['yacht_model_id' => $model]);

                DB::table('foreign_regatta_yachts')
                    ->where('division_id', $division->id)
                    ->update(['yacht_model_id' => null]);
            });
    }

    public function down(): void
    {
        Schema::table('foreign_regatta_divisions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('yacht_model_id');
        });
    }
};
