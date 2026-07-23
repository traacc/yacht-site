<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Expense;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 22;

    public static function getModelLabel(): string
    {
        return 'Расход';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Расходы';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Введите название расхода')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                FileUpload::make('document')
                    ->label('Документ')
                    ->disk('public')
                    ->directory('expenses')
                    ->visibility('public')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/heic', 'image/heif'])
                    ->maxSize(10240)
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('document')
                    ->label('Документ')
                    ->formatStateUsing(fn ($state) => $state ? 'Прикреплён' : '—')
                    ->url(fn (Expense $record) => $record->document_url, shouldOpenInNewTab: true)
                    ->color(fn ($state) => $state ? 'primary' : 'gray')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Расходов пока нет')
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать расход'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenses::route('/'),
        ];
    }
}
