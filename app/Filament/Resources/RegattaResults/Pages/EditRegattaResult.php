<?php

namespace App\Filament\Resources\RegattaResults\Pages;

use App\Filament\Resources\RegattaResults\RegattaResultResource;
use App\Models\RegattaResult;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Отдельная страница редактирования результата регаты.
 *
 * Модалка для этой формы не подходит: таблица ввода результатов содержит по две
 * колонки на каждую гонку регаты и на широких регатах не помещается в диалог.
 */
class EditRegattaResult extends EditRecord
{
    protected static string $resource = RegattaResultResource::class;

    /**
     * Дегидрированные данные формы. EditRecord нигде их не сохраняет, а хук
     * afterSave() их не получает — перехватываем в mutateFormDataBeforeSave(),
     * иначе виртуальные поля таблицы (race_*, race_event_ids) до сохранения
     * результатов гонок не доедут.
     *
     * @var array<string, mixed>
     */
    protected array $savedFormData = [];

    public function getTitle(): string
    {
        return 'Результаты: '.($this->getRecord()->regatta?->name ?? 'регата не выбрана');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->savedFormData = $data;

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var RegattaResult $record */
        $record = $this->getRecord();

        RegattaResultResource::afterSave($record, $this->savedFormData);

        // Итоговые очки и места пересчитаны в БД уже после дегидрации формы —
        // без перезаполнения страница показывала бы старые значения. Заодно
        // обновляются колонки гонок, если гонки правили в этом же сохранении.
        // Тумблер блокировки не является атрибутом модели и при fill() сбросился
        // бы к default(false) — возвращаем выбор пользователя.
        $lockFilled = $this->data['lock_filled'] ?? false;

        $this->fillForm();

        $this->data['lock_filled'] = $lockFilled;
    }
}
