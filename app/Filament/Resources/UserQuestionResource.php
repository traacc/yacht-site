<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserQuestions\Pages\ManageUserQuestions;
use App\Models\Faq;
use App\Models\UserQuestion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserQuestionResource extends Resource
{
    use \App\Filament\Concerns\RestrictsAccessByRole;

    protected static ?string $model = UserQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Вопросы пользователей';

    protected static ?int $navigationSort = 16;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    public static function getModelLabel(): string
    {
        return 'вопрос';
    }

    public static function getPluralModelLabel(): string
    {
        return 'вопросы пользователей';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = UserQuestion::unanswered()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('user_name')
                    ->label('Автор вопроса')
                    ->content(fn (?UserQuestion $record) => $record?->user?->name ?? '—'),

                Textarea::make('question')
                    ->label('Вопрос')
                    ->disabled()
                    ->rows(4)
                    ->columnSpanFull(),

                RichEditor::make('answer')
                    ->label('Ответ')
                    ->placeholder('Введите ответ пользователю')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Автор')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('question')
                    ->label('Вопрос')
                    ->limit(80)
                    ->searchable()
                    ->wrap(),

                IconColumn::make('answer')
                    ->label('Отвечено')
                    ->boolean()
                    ->state(fn (UserQuestion $record): bool => $record->isAnswered()),

                IconColumn::make('imported_to_faq')
                    ->label('В FAQ')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Задан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('answered')
                    ->label('Статус ответа')
                    ->placeholder('Все')
                    ->trueLabel('Отвечённые')
                    ->falseLabel('Без ответа')
                    ->queries(
                        true: fn (Builder $query) => $query->answered(),
                        false: fn (Builder $query) => $query->unanswered(),
                    ),
            ])
            ->emptyStateHeading('Вопросов пока нет')
            ->recordActions([
                EditAction::make()
                    ->label('Ответить')
                    ->icon(Heroicon::PencilSquare)
                    ->modalHeading('Ответ на вопрос')
                    ->modalSubmitActionLabel('Сохранить ответ')
                    ->using(function (UserQuestion $record, array $data): UserQuestion {
                        $answered = filled($data['answer'] ?? null);

                        $record->update([
                            'answer' => $data['answer'] ?? null,
                            'answered_at' => $answered ? now() : null,
                            'answered_by' => $answered ? auth()->id() : null,
                        ]);

                        return $record;
                    }),

                Action::make('importToFaq')
                    ->label('В FAQ')
                    ->icon(Heroicon::OutlinedQuestionMarkCircle)
                    ->color('success')
                    ->visible(fn (UserQuestion $record): bool => $record->isAnswered() && ! $record->imported_to_faq)
                    ->requiresConfirmation()
                    ->modalHeading('Импорт в FAQ')
                    ->modalDescription('Вопрос и ответ будут добавлены в раздел FAQ на сайте.')
                    ->modalSubmitActionLabel('Импортировать')
                    ->action(function (UserQuestion $record): void {
                        Faq::create([
                            'question' => mb_substr($record->question, 0, 500),
                            'answer' => (string) $record->answer,
                            'is_active' => true,
                            'sort_order' => (int) (Faq::max('sort_order') ?? 0) + 1,
                        ]);

                        $record->update(['imported_to_faq' => true]);

                        Notification::make()
                            ->title('Вопрос добавлен в FAQ')
                            ->success()
                            ->send();
                    }),

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
            'index' => ManageUserQuestions::route('/'),
        ];
    }
}
