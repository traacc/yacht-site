<?php

namespace App\Filament\Resources\Helps\Pages;

use App\Filament\Pages\HelpPageSettings;
use App\Filament\Resources\Helps\HelpResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHelps extends ManageRecords
{
    protected static string $resource = HelpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pageSettings')
                ->label('Настройки страницы')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('white')
                ->url(fn () => HelpPageSettings::getUrl()),
            CreateAction::make()->createAnother(false)->modalHeading('Новый раздел помощи'),
        ];
    }
}
