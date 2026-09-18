<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AdvertType;
use App\Filament\Resources\Adverts\AdvertResource;
use App\Models\Advert;
use App\Support\AccessControl;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Объявления, ждущие модерации, — по плитке на каждую доску.
 *
 * Плитка ведёт в список объявлений с фильтром по разделу и «только на
 * модерации», чтобы модератор сразу попадал в свою очередь.
 */
class AdvertsOverview extends BaseWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Объявления на модерации';

    protected array|int|null $columns = [
        'default' => 1,
        'sm' => 2,
        'md' => 3,
    ];

    public static function canView(): bool
    {
        return AccessControl::allows(AdvertResource::class);
    }

    protected function getStats(): array
    {
        // Один запрос на все доски вместо count() на каждую.
        $pending = Advert::query()
            ->pending()
            ->toBase()
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return collect(AdvertType::cases())
            ->map(fn (AdvertType $type): Stat => $this->createStat(
                $type->label(),
                (int) ($pending[$type->value] ?? 0),
                AdvertResource::getUrl('index', [
                    'filters' => [
                        'type' => ['value' => $type->value],
                        'pending' => ['isActive' => true],
                    ],
                ]),
            ))
            ->all();
    }

    private function createStat(string $label, int $value, string $url): Stat
    {
        return Stat::make($label, $value)
            ->view('filament.widgets.custom-stat-card', [
                'label' => $label,
                'value' => $value,
                'icon' => 'dashboard_entry',
                'iconColor' => $value > 0 ? 'text-amber-500' : 'text-[#2D92CE]',
                'url' => $url,
            ]);
    }
}
