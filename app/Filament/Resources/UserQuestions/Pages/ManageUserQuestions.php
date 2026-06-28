<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserQuestions\Pages;

use App\Filament\Resources\UserQuestionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageUserQuestions extends ManageRecords
{
    protected static string $resource = UserQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
