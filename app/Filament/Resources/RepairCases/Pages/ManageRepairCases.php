<?php

declare(strict_types=1);

namespace App\Filament\Resources\RepairCases\Pages;

use App\Filament\Resources\RepairCases\RepairCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRepairCases extends ManageRecords
{
    protected static string $resource = RepairCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Добавить кейс'),
        ];
    }
}
