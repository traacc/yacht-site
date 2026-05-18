<?php

namespace App\Filament\User\Resources\Yachts;

use App\Filament\User\Resources\Yachts\Pages\ManageYachts;
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

use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

class YachtResource extends Resource
{
    protected static ?string $model = Yacht::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getModelLabel(): string
    {
        return 'Яхта'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Яхты'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('note_form')
                ->hiddenLabel()
                ->content(new HtmlString('Выберите яхту из базы Ассоциации или заполните данные вручную. Номер ВФПС будет использован как уникальный ID яхты в системе.'))
                ->columnSpanFull(), // Растягиваем на всю ширину
                Select::make('yacht_search')->placeholder('Номер ВФПС или название яхты')->columnSpanFull()->label('Найти яхту в базе')->searchable()
                ->getSearchResultsUsing(fn (string $search): array => \App\Models\Yacht::query()
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('vfps_number', 'like', "%{$search}%")
                    ->limit(50)
                    ->pluck('name', 'id')
                    ->toArray())
                ->getOptionLabelUsing(fn ($value): ?string => \App\Models\Yacht::find($value)?->name)
                ->live()
                ->afterStateUpdated(function ($state, $set) {
                    $yacht = \App\Models\Yacht::find($state);
                    if ($yacht) {
                        $set('name', $yacht->name);
                        $set('vfps_number', $yacht->vfps_number);
                        // Заполните остальные поля аналогично
                    }
                }),
                TextInput::make('name')
                    ->required()->label('Название яхты')->placeholder('Введите название яхты'),
                TextInput::make('gims_number')->label('Номер ГИМС')->placeholder('Введите номер ГИМС'),
                TextInput::make('vfps_number')
                    ->required()->unique(table: 'yachts', column: 'vfps_number', ignoreRecord: true)
                ->validationMessages([
                    'unique' => 'Яхта с таким номером ВФПС уже существует в системе.',
                ])->label('Номер паруса')->placeholder('Введите номер паруса (ВФПС)'),
                TextInput::make('class')->label('Класс')->placeholder('Введите класс яхты'),

                Placeholder::make('Параметры')->columnSpanFull(),
                TextInput::make('project')->label('Проект')->placeholder('Введите проект яхты'),
                TextInput::make('year')->label('Год выпуска')->placeholder('Введите год выпуска')
                    ->numeric(),
                TextInput::make('current_mass_kg')->label('Масса яхты')->placeholder('Введите массу яхты')->numeric(),
                TextInput::make('reg_place')->label('Место регистрации')->placeholder('Введите место регистрации'),

                Placeholder::make('owner_title')->label('Контакты собственика')->columnSpanFull(),
                TextInput::make('owner_name')->label('Имя владельца')->placeholder('Введите имя владельца яхты')->columnSpanFull(),
                TextInput::make('owner_phone')->label('Телефон владельца')->placeholder('Введите телефон владельца яхты'),
                TextInput::make('owner_email')->label('Email владельца')->placeholder('Введите email владельца яхты'),
                FileUpload::make('owner_photo')
                    ->label('Фото владельца')
                    ->image()
                    ->avatar()
                    ->directory('owners')
                    ->disk('public')
                    ->columnSpanFull(),

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
                                'ship_ticket' => 'Судовой билет',
                                'insurance' => 'Страховка',
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()->label('Название'),


                TextColumn::make('gims_number')
                    ->searchable()->label('Номер ГИМС'),
                TextColumn::make('vfps_number')
                    ->searchable()->label('Номер паруса'),


                TextColumn::make('approval_status')
                    ->badge()->label('Статус')->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending' => 'На проверке',
                    'approved' => 'Активная',
                    'rejected' => 'Отклонена',
                    default => $state,
                })->color(fn (string $state): string => match ($state) {
                    'pending' => 'gray',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
            ])
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

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

}
