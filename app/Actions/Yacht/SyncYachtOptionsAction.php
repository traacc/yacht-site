<?php

declare(strict_types=1);

namespace App\Actions\Yacht;

use App\Models\Yacht;
use App\Models\YachtOption;
use Filament\Forms\Components\Select;

/**
 * Связывает динамические поля формы яхты (по одному Select на каждую опцию)
 * со связью Yacht::optionValues(). На яхте можно выбрать не более одного
 * значения на каждую опцию (см. yacht_option_selections).
 */
final class SyncYachtOptionsAction
{
    public function fieldName(YachtOption $option): string
    {
        return 'option_'.$option->key;
    }

    /**
     * Select-компоненты формы, по одному на каждую опцию.
     *
     * @return array<int, Select>
     */
    public function formComponents(): array
    {
        return YachtOption::cachedAllWithValues()
            ->map(fn (YachtOption $option) => Select::make($this->fieldName($option))
                ->label($option->label)
                ->options($option->values->pluck('label', 'id'))
                ->placeholder('Не выбрано')
                ->searchable())
            ->all();
    }

    /**
     * Текущие значения опций яхты для заполнения формы: option_<key> => value_id.
     *
     * @return array<string, string|null>
     */
    public function load(Yacht $yacht): array
    {
        $selected = $yacht->optionValues()->get()->keyBy(fn ($value) => $value->pivot->yacht_option_id);

        $data = [];
        foreach (YachtOption::cachedAllWithValues() as $option) {
            $data[$this->fieldName($option)] = $selected->get($option->id)?->id;
        }

        return $data;
    }

    /**
     * Извлекает поля опций (option_<key>) из данных формы и удаляет их из массива,
     * чтобы они не попали в Yacht::update().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string|null> option_id => value_id|null
     */
    public function extract(array &$data): array
    {
        $selections = [];
        foreach (YachtOption::cachedAllWithValues() as $option) {
            $field = $this->fieldName($option);
            if (array_key_exists($field, $data)) {
                $selections[$option->id] = $data[$field];
                unset($data[$field]);
            }
        }

        return $selections;
    }

    /**
     * Сохраняет выбранные значения опций для яхты.
     *
     * @param  array<string, string|null>  $selections  option_id => value_id|null
     */
    public function execute(Yacht $yacht, array $selections): void
    {
        $sync = [];
        foreach ($selections as $optionId => $valueId) {
            if ($valueId) {
                $sync[$valueId] = ['yacht_option_id' => $optionId];
            }
        }

        $yacht->optionValues()->sync($sync);
    }
}
