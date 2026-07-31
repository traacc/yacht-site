<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\RentalRequestStatus;
use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Filament\Resources\RepairRequests\RepairRequestResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\RepairRequest;
use App\Models\ServiceRequest;
use App\Models\YachtRentalRequest;
use App\Support\AccessControl;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Необработанные заявки с сайта на дашборде.
 *
 * ТЗ 3-го этапа требует, чтобы запрос уходил в почту, в админпанель и на
 * дашборд; последнего не было ни у одной формы. Отдельный виджет, а не пятая
 * плитка в StatsOverview: там счётчики по ассоциации со своей логикой доступа,
 * а здесь — рабочая очередь менеджера.
 */
class RequestsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected array|int|null $columns = [
        'default' => 1,
        'sm' => 2,
        'md' => 3,
    ];

    public static function canView(): bool
    {
        return AccessControl::allows(ServiceRequestResource::class)
            || AccessControl::allows(RentalRequestResource::class)
            || AccessControl::allows(RepairRequestResource::class);
    }

    protected function getStats(): array
    {
        $stats = [];

        // Каждая плитка ведёт в свой раздел, поэтому показываем только те,
        // что роль вправе открыть.
        if (AccessControl::allows(ServiceRequestResource::class)) {
            $stats[] = $this->createStat(
                'Заявки на услуги',
                ServiceRequest::query()->new()->count(),
                ServiceRequestResource::getUrl('index'),
            );
        }

        if (AccessControl::allows(RentalRequestResource::class)) {
            $stats[] = $this->createStat(
                'Заявки на аренду',
                YachtRentalRequest::where('status', RentalRequestStatus::Pending)->count(),
                RentalRequestResource::getUrl('index'),
            );
        }

        if (AccessControl::allows(RepairRequestResource::class)) {
            $stats[] = $this->createStat(
                'Заявки на ремонт',
                RepairRequest::query()->pending()->count(),
                RepairRequestResource::getUrl('index'),
            );
        }

        return $stats;
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
