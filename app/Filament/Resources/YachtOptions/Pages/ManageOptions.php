<?php

declare(strict_types=1);

namespace App\Filament\Resources\YachtOptions\Pages;

use App\Filament\Resources\YachtOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOptions extends ManageRecords
{
    protected static string $resource = YachtOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Новая опция яхты')
                ->label('Добавить опцию')
                ->modalWidth('2xl')
                ->createAnother(false),
        ];
    }
}
