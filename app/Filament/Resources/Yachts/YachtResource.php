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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('vfps_number')
                    ->required(),
                TextInput::make('user_id'),
                TextInput::make('gims_number'),
                TextInput::make('orc_cert_url')
                    ->url(),
                TextInput::make('class'),
                TextInput::make('project'),
                TextInput::make('year')
                    ->numeric(),
                TextInput::make('reg_place'),
                TextInput::make('current_mass_kg')
                    ->numeric(),
                Select::make('approval_status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                    ->default('pending')
                    ->required(),
                TextInput::make('rejection_reason'),
                TextInput::make('rejection_comment'),
                Toggle::make('is_archived')
                    ->required(),
                TextInput::make('owner_name'),
                TextInput::make('owner_email')
                    ->email(),
                TextInput::make('owner_phone')
                    ->tel(),
                FileUpload::make('owner_photo')
                    ->label('Фото владельца')
                    ->image()
                    ->avatar()
                    ->circleCrop()
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
                    ->searchable(),
                TextColumn::make('vfps_number')
                    ->searchable(),
                TextColumn::make('user_id')
                    ->searchable(),
                TextColumn::make('gims_number')
                    ->searchable(),
                TextColumn::make('orc_cert_url')
                    ->searchable(),
                TextColumn::make('class')
                    ->searchable(),
                TextColumn::make('project')
                    ->searchable(),
                TextColumn::make('year')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reg_place')
                    ->searchable(),
                TextColumn::make('current_mass_kg')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('approval_status')
                    ->badge(),
                TextColumn::make('rejection_reason')
                    ->searchable(),
                TextColumn::make('rejection_comment')
                    ->searchable(),
                IconColumn::make('is_archived')
                    ->boolean(),
                TextColumn::make('owner_name')
                    ->searchable(),
                TextColumn::make('owner_email')
                    ->searchable(),
                TextColumn::make('owner_phone')
                    ->searchable(),
                ImageColumn::make('owner_photo')
                    ->label('Фото')
                    ->circular(),
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
}
