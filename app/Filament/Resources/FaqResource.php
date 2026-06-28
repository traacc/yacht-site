<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class FaqResource extends Resource
{
    use \App\Filament\Concerns\RestrictsAccessByRole;

    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $navigationLabel = 'FAQ';

    protected static ?int $navigationSort = 15;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    public static function getModelLabel(): string
    {
        return 'вопрос';
    }

    public static function getPluralModelLabel(): string
    {
        return 'FAQ';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('question')
                    ->label('Вопрос')
                    ->placeholder('Введите вопрос')
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),

                RichEditor::make('answer')
                    ->label('Ответ')
                    ->placeholder('Введите развёрнутый ответ')
                    ->required()
                    ->columnSpanFull(),
                
                Toggle::make('is_active')
                    ->label('Показывать на сайте')
                    ->default(true),

                /*
                TextInput::make('sort_order')
                    ->label('Порядок')
                    ->helperText('Чем меньше число, тем выше в списке')
                    ->numeric()
                    ->default(0),
                */
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->label('Вопрос')
                    ->limit(80)
                    ->searchable()
                    ->wrap(),

                ToggleColumn::make('is_active')
                    ->label('На сайте'),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->emptyStateHeading('Вопросов пока нет')
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Редактировать вопрос')
                    ->modalSubmitActionLabel('Сохранить')
                    ->hiddenLabel(),

                DeleteAction::make()
                    ->modalHeading('Удалить вопрос')
                    ->modalDescription('Вы уверены, что хотите удалить этот вопрос?')
                    ->modalSubmitActionLabel('Удалить')
                    ->hiddenLabel(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFaqs::route('/'),
        ];
    }
}
