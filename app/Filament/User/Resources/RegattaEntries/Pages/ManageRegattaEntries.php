<?php

namespace App\Filament\User\Resources\RegattaEntries\Pages;

use App\Filament\User\Resources\RegattaEntries\RegattaEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegattaEntries extends ManageRecords
{
    protected static string $resource = RegattaEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(fn (array $data): array => array_merge($data, [
                    'status' => 'approved',
                ])),
        ];
    }
}
