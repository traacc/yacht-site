<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Три типа дивизиона вместо двух и ручной ввод модели у монотипа.
 *
 * Названия стали предметными: «флот одинаковых яхт» → «монотипный без выбора
 * яхт», «список конкретных яхт» → «гандикапный», между ними появился
 * «монотипный с выбором яхт» — общая цена, но лодки разных моделей.
 *
 * Заодно уходит ссылка дивизиона на справочник: у монотипа без выбора модель
 * теперь вводится строкой, а у монотипа с выбором модель своя у каждой лодки.
 * Чтобы витрина не потеряла характеристики, спецификация модели переносится в
 * собственные колонки дивизиона.
 *
 * Работаем query builder'ом, а не моделями: старые значения `type` под новый
 * enum уже не подходят, и каст на них падает.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Спецификация из справочника — в поля самого дивизиона.
        DB::table('foreign_regatta_divisions')
            ->whereNotNull('yacht_model_id')
            ->get()
            ->each(function (object $division): void {
                $model = DB::table('charter_yacht_models')->find($division->yacht_model_id);

                if ($model === null) {
                    return;
                }

                $filled = fn (?string $value): bool => trim((string) $value) !== '';

                DB::table('foreign_regatta_divisions')
                    ->where('id', $division->id)
                    ->update([
                        'model' => $filled($division->model) ? $division->model : $model->name,
                        'cabins' => $division->cabins ?? $model->cabins,
                        'downwind_sail' => $division->downwind_sail ?? $model->downwind_sail,
                        'description' => $filled($division->description) ? $division->description : $model->description,
                    ]);
            });

        DB::table('foreign_regatta_divisions')->where('type', 'fleet')->update(['type' => 'monotype']);
        DB::table('foreign_regatta_divisions')->where('type', 'list')->update(['type' => 'handicap']);

        Schema::table('foreign_regatta_divisions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('yacht_model_id');
            $table->string('type', 16)->default('monotype')->change();
        });
    }

    public function down(): void
    {
        Schema::table('foreign_regatta_divisions', function (Blueprint $table): void {
            $table->foreignUuid('yacht_model_id')
                ->nullable()
                ->after('name')
                ->constrained('charter_yacht_models')
                ->nullOnDelete();

            $table->string('type', 16)->default('fleet')->change();
        });

        // Монотип с выбором яхт прежней схеме неизвестен — ближайший по смыслу
        // тип там «список конкретных лодок».
        DB::table('foreign_regatta_divisions')->where('type', 'monotype')->update(['type' => 'fleet']);
        DB::table('foreign_regatta_divisions')
            ->whereIn('type', ['handicap', 'monotype_choice'])
            ->update(['type' => 'list']);
    }
};
