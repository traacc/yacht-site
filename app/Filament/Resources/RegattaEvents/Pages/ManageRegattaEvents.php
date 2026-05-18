<?php

namespace App\Filament\Resources\RegattaEvents\Pages;

use App\Filament\Resources\RegattaEvents\RegattaEventsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegattaEvents extends ManageRecords
{
    protected static string $resource = RegattaEventsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
