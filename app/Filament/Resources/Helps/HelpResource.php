<?php

namespace App\Filament\Resources\Helps;

use App\Filament\Resources\Helps\Pages\ManageHelps;
use App\Models\Help;
use App\Models\HelpCategory;
use BackedEnum;
use UnitEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HelpResource extends Resource
{
    protected static ?string $model = Help::class;

    protected static string|BackedEnum|null $navigationIcon = 'help';

    public static function getModelLabel(): string
    {
        return 'Помощь';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Помощь';
    }

    protected static ?int $navigationSort = 25;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('help_category_id')
                    ->label('Категория')
                    ->relationship('category', 'title')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('title')
                            ->label('Название категории')
                            ->placeholder('Введите название')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, callable $set) =>
                                $set('slug', $state ? Str::slug($state) : '')
                            ),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->placeholder('avtomaticheski-zapolnyaetsya')
                            ->required()
                            ->unique('help_category', 'slug'),
                    ])
                    ->createOptionModalHeading('Новая категория'),

                TextInput::make('title')
                    ->label('Заголовок')
                    ->placeholder('Введите заголовок')
                    ->required()
                    ->columnSpanFull(),

                Textarea::make('desc')
                    ->label('Описание')
                    ->placeholder('Краткое описание услуги')
                    ->columnSpanFull(),

                Repeater::make('includes')
                    ->label('Что включено')
                    ->simple(
                        TextInput::make('item')
                            ->label('Пункт')
                            ->required(),
                    )
                    ->addActionLabel('Добавить пункт')
                    ->defaultItems(0)
                    ->columnSpanFull(),

                Select::make('contact_type')
                    ->label('Тип контакта')
                    ->options([
                        'specialist' => 'Специалист',
                        'manager'    => 'Менеджер',
                    ])
                    ->default('specialist')
                    ->required()
                    ->live(),

                TextInput::make('specialist_name')
                    ->label('Имя специалиста')
                    ->placeholder('Иван Иванов'),

                TextInput::make('specialist_email')
                    ->label('Email специалиста')
                    ->email()
                    ->placeholder('example@mail.ru'),

                TextInput::make('specialist_phone')
                    ->label('Телефон специалиста')
                    ->telRegex('/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/')
                    ->mask('+7 (999) 999-99-99')
                    ->placeholder('+7 (999) 000-00-00'),

                TextInput::make('specialist_sphere')
                    ->label('Сфера деятельности')
                    ->placeholder('Юридические услуги'),

                TextInput::make('specialist_city')
                    ->label('Город')
                    ->placeholder('Москва'),

                Select::make('status')
                    ->label('Статус')
                    ->options([
                        'active'   => 'Активен',
                        'inactive' => 'Неактивен',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.title')
                    ->label('Категория')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('contact_type')
                    ->label('Тип контакта')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'specialist' => 'Специалист',
                        'manager'    => 'Менеджер',
                        default      => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'specialist' => 'info',
                        'manager'    => 'warning',
                        default      => 'gray',
                    }),

                TextColumn::make('specialist_name')
                    ->label('Специалист')
                    ->searchable(),

                TextColumn::make('specialist_city')
                    ->label('Город')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active'   => 'Активен',
                        'inactive' => 'Неактивен',
                        default    => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active'   => 'success',
                        'inactive' => 'danger',
                        default    => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->label('Удалён')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Записей пока нет')
            ->filters([
                SelectFilter::make('help_category_id')
                    ->label('Категория')
                    ->relationship('category', 'title')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('contact_type')
                    ->label('Тип контакта')
                    ->options([
                        'specialist' => 'Специалист',
                        'manager'    => 'Менеджер',
                    ]),

                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'active'   => 'Активен',
                        'inactive' => 'Неактивен',
                    ]),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать раздел о помощи'),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHelps::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
