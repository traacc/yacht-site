<?php

namespace App\Filament\Resources\RegattaResults\Pages;

use App\Filament\Resources\RegattaResults\RegattaResultResource;
use App\Models\RegattaResult;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRegattaResults extends ListRecords
{
    protected static string $resource = RegattaResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->modalHeading('Новые результаты регаты')
                // Участников на форме создания заполнить нечем (таблица ввода
                // строится по сохранённой записи), поэтому список формируем по
                // активным заявкам сразу после создания и открываем таблицу.
                ->after(function (RegattaResult $record): void {
                    $created = RegattaResultResource::createItemsFromEntries($record);

                    Notification::make()
                        ->title($created > 0
                            ? 'Добавлено участников по заявкам: '.$created
                            : 'Активных заявок на эту регату нет')
                        ->success()
                        ->send();
                })
                ->successRedirectUrl(fn (RegattaResult $record): string => RegattaResultResource::getUrl('edit', ['record' => $record])),

        ];
    }
}
