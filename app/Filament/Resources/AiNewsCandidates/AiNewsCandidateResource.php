<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiNewsCandidates;

use App\Actions\WorldNews\PublishAiNewsCandidateAction;
use App\Actions\WorldNews\RefreshAiNewsCandidateImageAction;
use App\Actions\WorldNews\RejectAiNewsCandidateAction;
use App\Actions\WorldNews\RestoreAiNewsCandidateAction;
use App\Enums\AiNewsCandidateStatus;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\AiNewsCandidates\Pages\ManageAiNewsCandidates;
use App\Models\AiNewsCandidate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use UnitEnum;

class AiNewsCandidateResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = AiNewsCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'AI-новости';

    protected static ?int $navigationSort = 9;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    public static function getModelLabel(): string
    {
        return 'Кандидат в новости';
    }

    public static function getPluralModelLabel(): string
    {
        return 'AI-новости';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Материал')
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('summary')
                            ->label('Краткое описание')
                            ->rows(4)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        RichEditor::make('content')
                            ->label('Текст новости')
                            ->required()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('ai-news/inline')
                            ->fileAttachmentsVisibility('public')
                            ->columnSpanFull(),
                    ]),

                Section::make('Источник')
                    ->schema([
                        TextInput::make('source_name')
                            ->label('Источник')
                            ->required()
                            ->maxLength(255),

                        DateTimePicker::make('source_published_at')
                            ->label('Дата публикации источника')
                            ->displayFormat('d.m.Y H:i')
                            ->seconds(false),

                        TextInput::make('source_url')
                            ->label('Ссылка на оригинал')
                            ->url()
                            ->required()
                            ->maxLength(2048)
                            ->columnSpanFull(),

                        TextInput::make('image_url')
                            ->label('Превью-картинка')
                            ->helperText('Ссылка со страницы источника (og:image). При публикации файл скачивается и становится обложкой новости; очистите поле, чтобы опубликовать без обложки.')
                            ->url()
                            ->maxLength(2048)
                            ->columnSpanFull(),

                        Placeholder::make('image_preview')
                            ->label('Как выглядит превью')
                            ->content(fn (?AiNewsCandidate $record): HtmlString => new HtmlString(
                                $record?->image_url
                                    ? sprintf(
                                        '<img src="%s" alt="" style="max-height:220px;border-radius:8px" '
                                        .'onerror="this.replaceWith(document.createTextNode(\'Картинка недоступна\'))">',
                                        e($record->image_url),
                                    )
                                    : 'Картинка не найдена',
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Оценка AI')
                    ->schema([
                        TextInput::make('relevance_score')
                            ->label('Релевантность')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),

                        Textarea::make('selection_reason')
                            ->label('Почему материал выбран')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Состояние')
                    ->schema([
                        Placeholder::make('candidate_status')
                            ->label('Статус')
                            ->content(fn (?AiNewsCandidate $record): string => $record?->status?->label() ?? '—'),

                        Placeholder::make('discovered')
                            ->label('Найдено')
                            ->content(fn (?AiNewsCandidate $record): string => $record?->discovered_at?->format('d.m.Y H:i') ?? '—'),

                        Placeholder::make('published_news')
                            ->label('Созданная новость')
                            ->content(fn (?AiNewsCandidate $record): string => $record?->news_id !== null
                                ? (string) $record->news_id
                                : '—'),

                        Placeholder::make('published')
                            ->label('Опубликовано')
                            ->content(fn (?AiNewsCandidate $record): string => $record?->published_at?->format('d.m.Y H:i') ?? '—'),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (AiNewsCandidateStatus $state): string => $state->label())
                    ->color(fn (AiNewsCandidateStatus $state): string => $state->color())
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(100),

                TextColumn::make('source_published_at')
                    ->label('Дата источника')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),

            ])
            ->defaultSort('discovered_at', 'desc')
            ->stackedOnMobile()
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(collect(AiNewsCandidateStatus::cases())
                        ->mapWithKeys(fn (AiNewsCandidateStatus $status): array => [
                            $status->value => $status->label(),
                        ])
                        ->all()),

                SelectFilter::make('source_name')
                    ->label('Источник')
                    ->options(fn (): array => AiNewsCandidate::query()
                        ->whereNotNull('source_name')
                        ->orderBy('source_name')
                        ->pluck('source_name', 'source_name')
                        ->all())
                    ->searchable(),

                Filter::make('source_published_at')
                    ->label('Дата источника')
                    ->schema([
                        DatePicker::make('from')
                            ->label('С'),
                        DatePicker::make('until')
                            ->label('По'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('source_published_at', '>=', $date),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('source_published_at', '<=', $date),
                        )),
            ])
            ->emptyStateHeading('AI-кандидатов пока нет')
            ->emptyStateDescription('Запустите поиск в настройках AI-новостей — найденные материалы появятся здесь.')
            ->recordActions([
                Action::make('openSource')
                    ->label('Оригинал')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (AiNewsCandidate $record): string => $record->source_url)
                    ->openUrlInNewTab(),

                Action::make('publish')
                    ->label('Опубликовать')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (AiNewsCandidate $record): bool => $record->canBePublished())
                    ->requiresConfirmation()
                    ->modalHeading('Опубликовать новость?')
                    ->modalDescription('Будет создана обычная новость сайта с отредактированным содержимым кандидата.')
                    ->modalSubmitActionLabel('Опубликовать')
                    ->action(function (AiNewsCandidate $record): void {
                        app(PublishAiNewsCandidateAction::class)->handle($record, auth()->id());
                        $record->refresh();

                        Notification::make()
                            ->title('Новость опубликована')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Отклонить')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (AiNewsCandidate $record): bool => $record->status === AiNewsCandidateStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading('Отклонить кандидат?')
                    ->modalSubmitActionLabel('Отклонить')
                    ->action(function (AiNewsCandidate $record): void {
                        app(RejectAiNewsCandidateAction::class)->handle($record);

                        Notification::make()
                            ->title('Кандидат отклонён')
                            ->success()
                            ->send();
                    }),

                Action::make('restore')
                    ->label('Вернуть на модерацию')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->visible(fn (AiNewsCandidate $record): bool => $record->status === AiNewsCandidateStatus::Rejected
                        && $record->news_id === null)
                    ->action(function (AiNewsCandidate $record): void {
                        app(RestoreAiNewsCandidateAction::class)->handle($record);

                        Notification::make()
                            ->title('Кандидат возвращён на модерацию')
                            ->success()
                            ->send();
                    }),

                /*
                Action::make('refreshImage')
                    ->label('Найти картинку')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->color('gray')
                    ->visible(fn (AiNewsCandidate $record): bool => $record->status !== AiNewsCandidateStatus::Published)
                    ->action(function (AiNewsCandidate $record): void {
                        $imageUrl = app(RefreshAiNewsCandidateImageAction::class)->handle($record);

                        if ($imageUrl === null) {
                            Notification::make()
                                ->title('Картинку найти не удалось')
                                ->body('На странице источника нет подходящего изображения. Прежнее значение оставлено без изменений.')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Картинка обновлена')
                            ->success()
                            ->send();
                    }),
                */
                EditAction::make()
                    ->label('Редактировать')
                    ->modalHeading('Редактировать AI-кандидат')
                    ->visible(fn (AiNewsCandidate $record): bool => $record->status !== AiNewsCandidateStatus::Published),

                ViewAction::make()
                    ->label('Просмотреть')
                    ->modalHeading('Опубликованный AI-кандидат')
                    ->visible(fn (AiNewsCandidate $record): bool => $record->status === AiNewsCandidateStatus::Published),
            ])
            ->toolbarActions([
                BulkAction::make('restoreCandidates')
                    ->label('Вернуть на модерацию')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Вернуть отклонённые на модерацию?')
                    ->modalDescription('Из выбранных будут затронуты только отклонённые кандидаты: они снова попадут в очередь на публикацию.')
                    ->modalSubmitActionLabel('Вернуть')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $action = app(RestoreAiNewsCandidateAction::class);
                        $restored = 0;

                        foreach ($records as $record) {
                            if ($record->status !== AiNewsCandidateStatus::Rejected || $record->news_id !== null) {
                                continue;
                            }

                            $action->handle($record);
                            $restored++;
                        }

                        Notification::make()
                            ->title("Возвращено на модерацию: {$restored} из {$records->count()}")
                            ->success()
                            ->send();
                    }),

                BulkAction::make('refreshImages')
                    ->label('Найти картинки')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Найти картинки заново?')
                    ->modalDescription('Для каждого выбранного кандидата будет заново открыта страница источника. Кандидаты, у которых картинку найти не удалось, останутся с прежним значением.')
                    ->modalSubmitActionLabel('Найти')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $action = app(RefreshAiNewsCandidateImageAction::class);
                        $updated = 0;

                        foreach ($records as $record) {
                            if ($record->status === AiNewsCandidateStatus::Published) {
                                continue;
                            }

                            if ($action->handle($record) !== null) {
                                $updated++;
                            }
                        }

                        Notification::make()
                            ->title("Картинки обновлены: {$updated} из {$records->count()}")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAiNewsCandidates::route('/'),
        ];
    }
}
