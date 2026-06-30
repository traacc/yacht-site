<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Questions\Pages;

use App\Filament\User\Resources\Questions\QuestionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageQuestions extends ManageRecords
{
    protected static string $resource = QuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
