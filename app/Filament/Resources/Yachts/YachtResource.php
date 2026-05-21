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
                    ->placeholder('Название яхты')
                    ->required(),
                TextInput::make('vfps_number')
                    ->label('Номер ВФПС')
                    ->placeholder('Номер ВФПС')
                    ->required(),
                TextInput::make('user_id')
                    ->label('Пользователь (ID)')
                    ->placeholder('ID пользователя'),
                TextInput::make('gims_number')
                    ->label('Номер ГИМС')
                    ->placeholder('Номер ГИМС'),
                TextInput::make('orc_cert_url')
                    ->label('ORC-сертификат (URL)')
                    ->placeholder('https://example.com/cert.pdf')
                    ->url(),
                TextInput::make('class')
                    ->label('Класс')
                    ->placeholder('Класс яхты'),
                TextInput::make('project')
                    ->label('Проект')
                    ->placeholder('Проект яхты'),
                TextInput::make('year')
                    ->label('Год выпуска')
                    ->placeholder('Год выпуска')
                    ->numeric(),
                TextInput::make('reg_place')
                    ->label('Место регистрации')
                    ->placeholder('Место регистрации'),
                TextInput::make('current_mass_kg')
                    ->label('Масса (кг)')
                    ->placeholder('Масса в кг')
                    ->numeric(),
                Select::make('approval_status')
                    ->label('Статус одобрения')
                    ->placeholder('Выберите статус')
                    ->options(['pending' => 'На рассмотрении', 'approved' => 'Одобрена', 'rejected' => 'Отклонена'])
                    ->default('pending')
                    ->required(),
                TextInput::make('rejection_reason')
                    ->label('Причина отклонения')
                    ->placeholder('Причина отклонения'),
                TextInput::make('rejection_comment')
                    ->label('Комментарий к отклонению')
                    ->placeholder('Комментарий к отклонению'),
                Toggle::make('is_archived')
                    ->label('Архивная'),
                TextInput::make('owner_name')
                    ->label('Имя владельца')
                    ->placeholder('Имя владельца'),
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
                    ->disk('public'),
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
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                TextColumn::make('vfps_number')
                    ->label('Номер ВФПС')
                    ->searchable(),
                TextColumn::make('user_id')
                    ->label('Пользователь (ID)')
                    ->searchable(),
                TextColumn::make('gims_number')
                    ->label('Номер ГИМС')
                    ->searchable(),
                TextColumn::make('orc_cert_url')
                    ->label('ORC-сертификат')
                    ->searchable(),
                TextColumn::make('class')
                    ->label('Класс')
                    ->searchable(),
                TextColumn::make('project')
                    ->label('Проект')
                    ->searchable(),
                TextColumn::make('year')
                    ->label('Год выпуска')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reg_place')
                    ->label('Место регистрации')
                    ->searchable(),
                TextColumn::make('current_mass_kg')
                    ->label('Масса (кг)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('approval_status')
                    ->label('Статус')
                    ->badge(),
                TextColumn::make('rejection_reason')
                    ->label('Причина отклонения')
                    ->searchable(),
                TextColumn::make('rejection_comment')
                    ->label('Комментарий')
                    ->searchable(),
                IconColumn::make('is_archived')
                    ->label('Архивная')
                    ->boolean(),
                TextColumn::make('owner_name')
                    ->label('Владелец')
                    ->searchable(),
                TextColumn::make('owner_email')
                    ->label('Email владельца')
                    ->searchable(),
                TextColumn::make('owner_phone')
                    ->label('Телефон владельца')
                    ->searchable(),
                ImageColumn::make('owner_photo')
                    ->label('Фото')
                    ->circular(),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Удалено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                TrashedFilter::make(),
            ])
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
