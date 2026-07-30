<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faqs\Pages;

use App\Filament\Resources\FaqResource;
use App\Models\Faq;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFaqs extends ManageRecords
{
    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Новый вопрос')
                ->label('Добавить вопрос')
                ->createAnother(false)
                // Порядок задаётся drag&drop, поля в форме нет: без этого новая
                // запись получала sort_order = 0 и прыгала в начало списка.
                ->mutateDataUsing(fn (array $data): array => [
                    ...$data,
                    'sort_order' => (int) (Faq::max('sort_order') ?? 0) + 1,
                ]),
        ];
    }
}
