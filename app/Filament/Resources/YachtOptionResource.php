<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\YachtOptions\Pages\ManageOptions;
use App\Models\YachtOption;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class YachtOptionResource extends Resource
{
    use \App\Filament\Concerns\RestrictsAccessByRole;

    protected static ?string $model = YachtOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Опции яхт';

    protected static ?string $title = 'Опции яхт';

    protected static ?int $navigationSort = 52;

    protected static string|UnitEnum|null $navigationGroup = 'Яхты';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return 'Опция яхты';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Опции яхт';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Название')
                    ->placeholder('GPS-навигатор')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('key', $state ? Str::slug($state, '_') : null)),

                TextInput::make('sort_order')
                    ->label('Порядок')
                    ->numeric()
                    ->default(0),

                TextInput::make('key')
                    ->label('Ключ')
                    ->placeholder('sail_material')
                    ->required()
                    ->unique(table: 'yacht_options', column: 'key', ignoreRecord: true)
                    ->helperText('Уникальный строковый идентификатор. Только латиница, цифры и подчёркивание.')
                    ->regex('/^[a-z][a-z0-9_]+$/')
                    ->maxLength(100),

                Repeater::make('values')
                    ->relationship()
                    ->label('Значения')
                    ->helperText('Варианты, из которых можно будет выбрать значение этой опции на странице яхты.')
                    ->addActionLabel('Добавить значение')
                    ->collapsible()
                    ->defaultItems(0)
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Новое значение')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('label')
                            ->label('Название')
                            ->placeholder('Дакрон')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('key', $state ? Str::slug($state, '_') : null)),

                        TextInput::make('key')
                            ->label('Ключ')
                            ->placeholder('dacron')
                            ->required()
                            ->regex('/^[a-z][a-z0-9_]+$/')
                            ->maxLength(100),

                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(3),
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

                TextColumn::make('values_count')
                    ->label('Значений')
                    ->state(fn (YachtOption $record): int => $record->values()->count()),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->emptyStateHeading('Опций яхты пока нет')
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Редактировать опцию')
                    ->modalSubmitActionLabel('Сохранить')
                    ->modalWidth('2xl')
                    ->hiddenLabel(),

                DeleteAction::make()
                    ->modalHeading('Удалить опцию')
                    ->modalDescription('Вы уверены, что хотите удалить эту опцию?')
                    ->modalSubmitActionLabel('Удалить')
                    ->hiddenLabel()
                    ->before(function (DeleteAction $action) {
                        /** @var YachtOption $record */
                        $record = $action->getRecord();
                        if ($record->isUsed()) {
                            Notification::make()
                                ->title("Опция «{$record->label}» используется в {$record->usageCount()} яхтах и не может быть удалена.")
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
            'index' => ManageOptions::route('/'),
        ];
    }
}
