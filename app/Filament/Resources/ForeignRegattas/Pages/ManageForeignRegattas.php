<?php

declare(strict_types=1);

namespace App\Filament\Resources\ForeignRegattas\Pages;

use App\Filament\Resources\ForeignRegattas\ForeignRegattaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageForeignRegattas extends ManageRecords
{
    protected static string $resource = ForeignRegattaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Добавить регату'),
        ];
    }
}
