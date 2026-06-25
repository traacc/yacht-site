<?php

declare(strict_types=1);

namespace App\Filament\Resources\FinancialReports\Pages;

use App\Filament\Resources\FinancialReports\FinancialReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFinancialReports extends ManageRecords
{
    protected static string $resource = FinancialReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Новый финансовый отчёт')
                ->createAnother(false),
        ];
    }
}
