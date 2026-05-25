<?php

declare(strict_types=1);

namespace App\Filament\Resources\Yachts\Pages;

use App\Filament\Resources\Yachts\YachtResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageYachts extends ManageRecords
{
    protected static string $resource = YachtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('documentSettings')
                ->label('Обязательные документы')
                ->icon('heroicon-o-document-check')
                ->color('white')
                ->url(fn () => \App\Filament\Pages\YachtDocumentSettings::getUrl()),
            CreateAction::make(),
        ];
    }
}
