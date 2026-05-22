<?php

namespace App\Filament\Resources\Yachts;

use App\Filament\Resources\Yachts\Pages\ManageYachts;
use App\Models\Yacht;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;

use Filament\Schemas\Components\Utilities\Get;

class YachtResource extends Resource
{
    public static function getModelLabel(): string
    {
        return 'Яхта'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Яхты'; // Название во множественном числе
    }

    protected static ?string $model = Yacht::class;

    protected static string|BackedEnum|null $navigationIcon = 'yacht';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Введите название яхты')
                    ->required(),
                TextInput::make('gims_number')
                    ->label('Номер ГИМС')
                    ->placeholder('Введите номер ГИМС'),
                TextInput::make('vfps_number')
                    ->label('Номер на парусе')
                    ->placeholder('Введите номер на парусе')
                    ->required(),
                Select::make('user_id')
                    ->label('Пользователь')
                    ->relationship('user', 'name')
                    ->placeholder('Пользователь зарегистрировавший яхту'),

                TextInput::make('project')
                    ->label('Проект')
                    ->placeholder('Проект яхты'),
                TextInput::make('year')
                    ->label('Год выпуска')
                    ->placeholder('Год выпуска')
                    ->numeric(),
                TextInput::make('current_mass_kg')
                    ->label('Масса (кг)')
                    ->placeholder('Масса в кг')
                    ->numeric(),
                TextInput::make('class')
                    ->label('Класс')
                    ->placeholder('Класс яхты'),


                TextInput::make('reg_place')
                    ->label('Место регистрации')
                    ->placeholder('Место регистрации'),

                Select::make('approval_status')
                    ->label('Статус одобрения')
                    ->placeholder('Выберите статус')
                    ->options(['pending' => 'На рассмотрении', 'approved' => 'Одобрена', 'rejected' => 'Отклонена'])
                    ->default('pending')
                    ->required(),
                TextInput::make('owner_name')
                    ->label('Имя владельца')
                    ->placeholder('Имя владельца')->columnSpanFull(),
                TextInput::make('owner_email')
                    ->label('Email владельца')
                    ->placeholder('email@example.com')
                    ->email(),
                TextInput::make('owner_phone')
                    ->label('Телефон владельца')
                    ->placeholder('+7 (999) 123-45-67')
                    ->tel(),
                FileUpload::make('owner_photo')
                    ->label('Фото владельца')
                    ->image()
                    ->avatar()
                    ->directory('owners')
                    ->disk('public')->columnSpanFull(),
                Repeater::make('documents')
                    ->relationship()
                    ->label('Документы')
                    ->addActionLabel('Добавить документ')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('doc_type')
                            ->label('Тип')
                            ->options([
                                'orc_certificate' => 'ORC-сертификат',
                                'regulation' => 'Положение',
                                'race_instructions' => 'Гоночная инструкция',
                                'charter' => 'Устав',
                                'protocol' => 'Протокол',
                                'other' => 'Прочее',
                            ])
                            ->default('other')
                            ->required(),
                        TextInput::make('title')
                            ->label('Название')
                            ->placeholder('Название документа')
                            ->required(),
                        FileUpload::make('url')
                            ->label('Файл')
                            ->directory('documents')
                            ->disk('public')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(20480)
                            ->downloadable()
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Яхта')
                    ->searchable(),
                TextColumn::make('gims_number')
                    ->label('Номер ГИМС')
                    ->searchable(),
                TextColumn::make('vfps_number')
                    ->label('Парус №')
                    ->searchable(),
                TextColumn::make('owner_name')
                    ->label('Владелец')
                    ->searchable(),
                TextColumn::make('orc_cert')
                    ->label('ORC-сертификат')
                    ->state(function ($record) {
                    // Проверяем, существует ли связанный документ с нужным doc_type
                    return $record->documents() // Название вашей связи в модели
                        ->where('doc_type', 'orc_cert_type') // Ваше условие для doc_type
                        ->exists();
                })
                ->formatStateUsing(fn ($state) => $state ? 'Есть' : 'Нет')
                ->color(fn ($state) => $state ? 'success' : 'danger'),

                TextColumn::make('approval_status')
                    ->label('Статус')
                    ->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending' => 'На рассмотрении',
                    'approved' => 'Одобрена',
                    'rejected' => 'Отклонена',
                    'withdrawn' => 'Отозвана',
                    default => $state,
                })
                ->color(fn (string $state): string => match ($state) {
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'withdrawn' => 'gray',
                    default => 'gray',
                }),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                SelectFilter::make('approval_status')
                ->label('Статус') // Красивое название для пользователя
                ->options([
                    'pending' => 'На рассмотрении',
                    'approved' => 'Одобрена',
                    'rejected' => 'Отклонена',
                    'withdrawn' => 'Отозвана',
                ])

            ], layout: FiltersLayout::AboveContent)->filtersFormColumns(3)->deferFilters(false)
            ->recordActions([
                EditAction::make(),
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
            'index' => ManageYachts::route('/'),
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
