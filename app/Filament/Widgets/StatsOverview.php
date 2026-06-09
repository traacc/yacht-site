<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Regattas\RegattaResource;
use App\Filament\Resources\PendingRegattaEntries\PendingRegattaEntryResource;
use App\Filament\Resources\RegattaEntries\RegattaEntryResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Yachts\YachtResource;
use App\Filament\Resources\Teams\TeamResource;
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
        $newEntriesCount = RegattaEntry::where('status', 'pending')
            ->whereHas('regatta', fn ($q) => $q->where('date_end', '>=', now()->toDateString()))
            ->count();

        return [
            $this->createCustomStat('Регаты', Regatta::count(), 'dashboard_regatta_count', 'text-blue-500/10', RegattaResource::getUrl('index')),
            $this->createCustomStat('Новые заявки', $newEntriesCount, 'dashboard_entry', $newEntriesCount > 0 ? 'text-amber-500' : 'text-[#2D92CE]', PendingRegattaEntryResource::getUrl('index')),
            $this->createCustomStat('Пользователи', User::count(), 'dashboard_users', 'text-emerald-500/10', UserResource::getUrl('index')),
            $this->createCustomStat('Яхты', Yacht::count(), 'dashboard_yachts', 'text-cyan-500/10', YachtResource::getUrl('index')),
            $this->createCustomStat('Команды', Team::count(), 'dashboard_teams', 'text-rose-500/10', TeamResource::getUrl('index')),
        ];
    }

    private function createCustomStat(string $label, int $value, string $icon, string $iconColorClass, ?string $url = null): Stat
    {
        return Stat::make($label, $value)
            ->view('filament.widgets.custom-stat-card', [
                'label' => $label,
                'value' => $value,
                'icon' => $icon,
                'iconColor' => $iconColorClass,
                'url' => $url,
            ]);
    }
}
