<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiNewsCandidates\Pages;

use App\Filament\Pages\AiNewsSettings;
use App\Filament\Resources\AiNewsCandidates\AiNewsCandidateResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageAiNewsCandidates extends ManageRecords
{
    protected static string $resource = AiNewsCandidateResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('aiNewsSettings')
                ->label('Настройки AI-новостей')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('white')
                ->visible(fn (): bool => AiNewsSettings::canAccess())
                ->url(fn (): string => AiNewsSettings::getUrl()),
        ];
    }
}
