<?php

declare(strict_types=1);

namespace App\Filament\Resources\FinancialReports;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\FinancialReports\Pages\ManageFinancialReports;
use App\Models\FinancialReport;
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
use UnitEnum;

class FinancialReportResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = FinancialReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 21;

    protected static string|UnitEnum|null $navigationGroup = 'Финансы';

    public static function getModelLabel(): string
    {
        return 'Финансовый отчёт';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Финансовые отчёты';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Введите название отчёта')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                FileUpload::make('document')
                    ->label('Документ')
                    ->disk('public')
                    ->directory('financial-reports')
                    ->visibility('public')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/avif', 'image/heic', 'image/heif'])
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
                    ->url(fn (FinancialReport $record) => $record->document_url, shouldOpenInNewTab: true)
                    ->color(fn ($state) => $state ? 'primary' : 'gray')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Финансовых отчётов пока нет')
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать финансовый отчёт'),
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
            'index' => ManageFinancialReports::route('/'),
        ];
    }
}
