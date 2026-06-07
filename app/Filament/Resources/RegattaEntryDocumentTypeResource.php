<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\DocumentOwner;
use App\Filament\Resources\RegattaEntryDocumentTypes\Pages\ManageRegattaEntryDocumentTypes;
use App\Models\YachtDocumentType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class RegattaEntryDocumentTypeResource extends Resource
{
    protected static ?string $model = YachtDocumentType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Типы документов заявок';

    protected static ?string $title = 'Типы документов заявок';

    protected static ?int $navigationSort = 52;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('owner', DocumentOwner::RegattaEntry->value);
    }

    public static function getModelLabel(): string
    {
        return 'Тип документов заявки';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Типы документов заявок';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('owner')->default(DocumentOwner::RegattaEntry->value),

                TextInput::make('label')
                    ->label('Название')
                    ->placeholder('Паспорт')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('key', $state ? Str::slug($state, '_') : null)),

                Textarea::make('description')
                    ->label('Описание')
                    ->rows(2)
                    ->maxLength(500),

                Toggle::make('is_configurable')
                    ->label('Настраиваемый')
                    ->helperText('Можно ли настроить обязательность этого типа документов')
                    ->default(true),

                TextInput::make('sort_order')
                    ->label('Порядок')
                    ->numeric()
                    ->default(0),

                TextInput::make('key')
                    ->label('Ключ')
                    ->placeholder('passport')
                    ->required()
                    ->unique(table: 'yacht_document_types', column: 'key', ignoreRecord: true)
                    ->helperText('Уникальный строковый идентификатор. Только латиница, цифры и подчёркивание.')
                    ->regex('/^[a-z][a-z0-9_]+$/')
                    ->maxLength(100),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->emptyStateHeading('Типов документов заявок пока нет')
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Редактировать тип документа')
                    ->modalSubmitActionLabel('Сохранить')
                    ->hiddenLabel(),

                DeleteAction::make()
                    ->modalHeading('Удалить тип документа')
                    ->modalDescription('Вы уверены, что хотите удалить этот тип документа?')
                    ->modalSubmitActionLabel('Удалить')
                    ->hiddenLabel()
                    ->before(function (DeleteAction $action) {
                        /** @var YachtDocumentType $record */
                        $record = $action->getRecord();
                        if ($record->isUsedInDocuments()) {
                            \Filament\Notifications\Notification::make()
                                ->title("Тип «{$record->label}» используется в {$record->usageCount()} документах и не может быть удалён.")
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegattaEntryDocumentTypes::route('/'),
        ];
    }
}
