<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class RentalCalendar extends Field
{
    protected string $view = 'filament.forms.components.rental-calendar';

    protected function setUp(): void
    {
        parent::setUp();

        // Состояние — массив периодов аренды той же формы, что читает syncRentals().
        $this->default([]);

        // Нормализуем перед сохранением: выкидываем неполные строки и пустые цены → null.
        $this->dehydrateStateUsing(function ($state): array {
            return collect(is_array($state) ? $state : [])
                ->filter(fn ($row) => ! empty($row['date_start']) && ! empty($row['date_end']))
                ->map(fn ($row) => [
                    'date_start'  => $row['date_start'],
                    'date_end'    => $row['date_end'],
                    'price_event' => ($row['price_event'] ?? '') === '' ? null : $row['price_event'],
                    'price_pro'   => ($row['price_pro'] ?? '') === '' ? null : $row['price_pro'],
                ])
                ->values()
                ->all();
        });
    }
}
