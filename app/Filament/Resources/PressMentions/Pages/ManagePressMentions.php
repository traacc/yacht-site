<?php

declare(strict_types=1);

namespace App\Filament\Resources\PressMentions\Pages;

use App\Filament\Resources\PressMentions\PressMentionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePressMentions extends ManageRecords
{
    protected static string $resource = PressMentionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Добавить публикацию'),
        ];
    }
}
