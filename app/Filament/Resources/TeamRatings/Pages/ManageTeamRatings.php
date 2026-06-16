<?php

namespace App\Filament\Resources\TeamRatings\Pages;

use App\Filament\Resources\TeamRatings\TeamRatingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTeamRatings extends ManageRecords
{
    protected static string $resource = TeamRatingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->createAnother(false)->modalHeading('Новый командный рейтинг'),
        ];
    }
}
