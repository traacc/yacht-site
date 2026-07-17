<?php

namespace App\Filament\Resources\PersonalRatings\Pages;

use App\Filament\Resources\PersonalRatings\PersonalRatingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePersonalRatings extends ManageRecords
{
    use \App\Filament\Concerns\RecalculatesRatings;

    protected static string $resource = PersonalRatingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->createAnother(false)->modalHeading('Новый личный рейтинг'),
            $this->recalculateRatingsAction(),
        ];
    }
}
