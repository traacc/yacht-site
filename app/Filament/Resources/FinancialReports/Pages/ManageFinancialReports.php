<?php

declare(strict_types=1);

namespace App\Filament\Resources\FinancialReports\Pages;

use App\Filament\Pages\FinancialPeriodReport;
use App\Filament\Resources\FinancialReports\FinancialReportResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageFinancialReports extends ManageRecords
{
    protected static string $resource = FinancialReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Отчёт за период формируется по реестру платежей — раздел доступен
            // только финансовому контуру, поэтому кнопка следует его правам.
            Action::make('buildPeriodReport')
                ->label('Сформировать за период')
                ->icon(Heroicon::OutlinedPresentationChartLine)
                ->color('gray')
                ->url(fn (): string => FinancialPeriodReport::getUrl())
                ->visible(fn (): bool => FinancialPeriodReport::canAccess()),
            CreateAction::make()
                ->modalHeading('Новый финансовый отчёт')
                ->createAnother(false),
        ];
    }
}
