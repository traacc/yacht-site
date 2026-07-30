<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Questions;

use App\Filament\User\Resources\Questions\Pages\ManageQuestions;
use App\Models\UserQuestion;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class QuestionResource extends Resource
{
    protected static ?string $model = UserQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'вопрос';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Мои вопросы';
    }

    public static function getNavigationLabel(): string
    {
        return 'Мои вопросы';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('question')
                    ->label('Ваш вопрос')
                    ->content(fn (UserQuestion $record): string => $record->question)
                    ->columnSpanFull(),

                Placeholder::make('answer')
                    ->label('Ответ администрации')
                    ->content(fn (UserQuestion $record): HtmlString => $record->isAnswered()
                        ? new HtmlString('<div class="prose prose-sm max-w-none">'.$record->answer.'</div>')
                        : new HtmlString('<span class="text-gray-500">Ответ ещё не получен. Мы ответим вам в ближайшее время.</span>'))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->label('Вопрос')
                    ->limit(80)
                    ->wrap(),

                IconColumn::make('answer')
                    ->label('Есть ответ')
                    ->boolean()
                    ->state(fn (UserQuestion $record): bool => $record->isAnswered()),

                TextColumn::make('created_at')
                    ->label('Задан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('answered_at')
                    ->label('Отвечен')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Вы пока не задавали вопросов')
            ->emptyStateDescription('Задать вопрос можно на главной странице сайта или в разделе «Помощь» → F.A.Q.')
            ->recordActions([
                ViewAction::make()
                    ->label('Посмотреть')
                    ->modalHeading('Вопрос и ответ')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageQuestions::route('/'),
        ];
    }
}
