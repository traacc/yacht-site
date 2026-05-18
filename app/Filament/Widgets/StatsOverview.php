<?php

namespace App\Filament\Widgets;

use App\Models\RegattaEntry;
use App\Models\Regatta;
use App\Models\Team;
use App\Models\User;
use App\Models\Yacht;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    // На мобильных — 1 колонка, на планшетах — 3, на десктопах — 5
    protected array | int | null $columns = [
        'default' => 1,
        'sm' => 2,
        'md' => 3,
        'xl' => 5,
    ];
    // Опционально: время кэширования виджета в секундах (полезно при больших объемах данных)
    // protected static ?string $pollingInterval = '15s'; 

    protected function getStats(): array
    {
        // Считаем "Новые заявки" за последние 3 дня (как пример)
        $newApplicationsCount = RegattaEntry::where('created_at', '>=', now()->subDays(3))->count();

        return [
            $this->createCustomStat('Регаты', Regatta::count(), 'heroicon-o-trophy', 'text-blue-500/10'),
            $this->createCustomStat('Новые заявки', RegattaEntry::where('status', 'new')->count(), 'heroicon-o-clipboard-document-list', 'text-amber-500/10'),
            $this->createCustomStat('Пользователи', User::count(), 'heroicon-o-users', 'text-emerald-500/10'),
            $this->createCustomStat('Яхты', Yacht::count(), 'heroicon-o-map', 'text-cyan-500/10'),
            $this->createCustomStat('Команды', Team::count(), 'heroicon-o-flag', 'text-rose-500/10'),
        ];
    }

    private function createCustomStat(string $label, int $value, string $icon, string $iconColorClass): Stat
    {
        return Stat::make($label, $value)
            ->view('filament.widgets.custom-stat-card', [
                'label' => $label,
                'value' => $value,
                'icon' => $icon,
                'iconColor' => $iconColorClass // Передаем цвет для иконки
            ]);
    }
}