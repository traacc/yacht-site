<?php

use App\Models\CharterYachtModel;
use App\Models\ForeignRegattaDivision;
use App\Models\ForeignRegattaYacht;
use Illuminate\Database\Migrations\Migration;

/**
 * Переносит уже заведённые лодки и монотипные дивизионы в справочник моделей.
 *
 * Без переноса справочник остался бы пустым, а накопленные регаты — в стороне
 * от него: выбирать было бы не из чего. Модели складываются по паре
 * «название модели + страна регаты», поэтому одна и та же лодка из двух регат
 * одной страны даст одну запись.
 *
 * Собственные поля лодок и дивизионов не очищаются: они приоритетнее
 * справочника (@see App\Models\ForeignRegattaYacht::spec()), и витрина
 * выглядит ровно как до миграции.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** @var array<string, CharterYachtModel> $cache */
        $cache = [];

        ForeignRegattaDivision::query()
            ->withTrashed()
            ->whereNull('yacht_model_id')
            ->with('regatta')
            ->get()
            ->each(function (ForeignRegattaDivision $division) use (&$cache): void {
                if (! $division->sharesSpec()) {
                    return;
                }

                $model = $this->resolve($cache, $division->model, $division->regatta?->country, [
                    'cabins' => $division->cabins,
                    'downwind_sail' => $division->downwind_sail?->value,
                    'description' => $division->description,
                ]);

                $model?->saveQuietly();

                if ($model !== null) {
                    $division->forceFill(['yacht_model_id' => $model->getKey()])->saveQuietly();
                }
            });

        ForeignRegattaYacht::query()
            ->withTrashed()
            ->whereNull('yacht_model_id')
            ->with(['regatta', 'division'])
            ->get()
            ->each(function (ForeignRegattaYacht $yacht) use (&$cache): void {
                // Лодка монотипного дивизиона своей модели не имеет — она уже
                // получила её через дивизион.
                if ($yacht->division?->sharesSpec()) {
                    return;
                }

                $model = $this->resolve($cache, $yacht->model, $yacht->regatta?->country, [
                    'cabins' => $yacht->cabins,
                    'downwind_sail' => $yacht->downwind_sail?->value,
                    'description' => $yacht->description,
                ]);

                if ($model !== null) {
                    $yacht->forceFill(['yacht_model_id' => $model->getKey()])->saveQuietly();
                }
            });
    }

    /**
     * Модель справочника под это название и страну — из кэша, базы или новая.
     *
     * @param  array<string, CharterYachtModel>  $cache
     * @param  array<string, mixed>  $attributes
     */
    private function resolve(array &$cache, ?string $name, ?string $country, array $attributes): ?CharterYachtModel
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $country = trim((string) $country) ?: null;
        $key = mb_strtolower($name).'|'.mb_strtolower((string) $country);

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $model = CharterYachtModel::query()
            ->where('name', $name)
            ->where(fn ($query) => $country === null
                ? $query->whereNull('country')
                : $query->where('country', $country))
            ->first();

        $model ??= CharterYachtModel::create(array_merge(
            array_filter($attributes, fn ($value): bool => $value !== null && $value !== ''),
            ['name' => $name, 'country' => $country],
        ));

        return $cache[$key] = $model;
    }

    public function down(): void
    {
        ForeignRegattaDivision::query()->withTrashed()->update(['yacht_model_id' => null]);
        ForeignRegattaYacht::query()->withTrashed()->update(['yacht_model_id' => null]);

        CharterYachtModel::query()->forceDelete();
    }
};
