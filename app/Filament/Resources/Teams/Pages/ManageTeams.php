<?php

namespace App\Filament\Resources\Teams\Pages;

use App\Actions\Team\CreateTeamAction;
use App\Filament\Resources\Teams\TeamResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTeams extends ManageRecords
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data, ManageRecords $livewire) => app(CreateTeamAction::class)->handle(
                    $data,
                    User::find($data['organizer_id']),
                )),
        ];
    }
}
