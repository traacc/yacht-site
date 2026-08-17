<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegattaResults\RelationManagers;

use App\Filament\Concerns\OverwritesRegattaEntries;
use App\Filament\Resources\RegattaEntries\RegattaEntryResource;
use App\Models\RegattaEntry;
use App\Models\RegattaResult;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Заявки на регату прямо на странице результата — вкладка рядом с таблицей
 * результатов. Состав участников результата формируется по этим заявкам, а
 * очки за буквенные статусы (DNF, DSQ…) зависят от их количества, поэтому
 * править заявки удобнее здесь же, не уходя в отдельный раздел.
 *
 * Форма и таблица берутся у ресурса заявок целиком, чтобы поведение
 * (документы, экипаж, подсветка неполных заявок) не разъезжалось.
 */
class EntriesRelationManager extends RelationManager
{
    use OverwritesRegattaEntries;

    protected static string $relationship = 'entries';

    protected static ?string $title = 'Заявки на регату';

    protected static ?string $modelLabel = 'заявка';

    protected static ?string $pluralModelLabel = 'заявки';

    public function form(Schema $schema): Schema
    {
        return RegattaEntryResource::form($schema);
    }

    public function table(Table $table): Table
    {
        /** @var RegattaResult $result */
        $result = $this->getOwnerRecord();

        return RegattaEntryResource::table($table)
            // Фильтр по регате бессмыслен: список и так ограничен регатой результата.
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->label('Добавить заявку')
                    ->modalHeading('Новая заявка на регату')
                    ->createAnother(false)
                    // fillForm с явными данными не применяет default() компонентов,
                    // поэтому набор документов формируем здесь же — иначе обязательные
                    // документы требуются валидацией, но полей загрузки в форме нет.
                    ->fillForm(fn (): array => [
                        'regatta_id' => $result->regatta_id,
                        'required_documents' => RegattaEntryResource::defaultRequiredDocuments($result->regatta_id),
                    ])
                    ->using(fn (array $data, Action $action): RegattaEntry => RegattaEntryResource::createFromFormData($data, $action)),
            ]);
    }
}
