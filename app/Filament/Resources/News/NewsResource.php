<?php

namespace App\Filament\Resources\News;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\News\Pages\ManageNews;
use App\Jobs\PublishNewsToTelegram;
use App\Jobs\PublishNewsToVk;
use App\Models\News;
use App\Services\TelegramService;
use App\Services\VkService;
use App\Support\RichContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class NewsResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = News::class;

    protected static string|BackedEnum|null $navigationIcon = 'news';

    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return 'Новость'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Новости'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Заголовок')
                    ->placeholder('Введите заголовок новости')
                    ->required(),
                RichEditor::make('content')
                    ->label('Содержание')
                    ->placeholder('Введите текст новости')
                    ->required()
                    // Диск задаём явно: FILESYSTEM_DISK=local, а картинки из текста
                    // должны лежать там же, откуда их отдаёт публичная страница.
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('news/inline')
                    ->fileAttachmentsVisibility('public')
                    // Список должен совпадать с App\Support\RichContent::BLOCKS.
                    ->customBlocks(RichContent::BLOCKS)
                    ->columnSpanFull(),
                FileUpload::make('cover_image_url')
                    ->label('Обложка')
                    ->helperText('После загрузки нажмите на карандаш, чтобы кадрировать, приблизить или повернуть изображение')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif'])
                    ->disk('public')
                    ->directory('news/covers')
                    ->visibility('public')
                    ->imageEditor()
                    ->imageEditorViewportWidth(1920)
                    ->imageEditorViewportHeight(840)
                    ->imageEditorAspectRatios([
                        '16:7',
                        '16:9',
                        '4:3',
                        '1:1',
                        null,
                    ]),
                DateTimePicker::make('published_at')
                    ->label('Дата публикации')
                    ->default(now())
                    ->required(),

                SpatieMediaLibraryFileUpload::make('gallery')
                    ->label('Галерея')
                    ->collection('gallery')
                    ->multiple()
                    ->reorderable()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif'])
                    ->imageEditor()
                    ->disk('public')
                    ->visibility('public')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable(),
                TextColumn::make('published_at')
                    ->label('Дата публикации')
                    ->dateTime()->dateTime('d.m.Y')
                    ->sortable(),
                IconColumn::make('published_to_tg')
                    ->label('В Telegram')
                    ->boolean(),
                IconColumn::make('published_to_vk')
                    ->label('В VK')
                    ->boolean(),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('publishToTelegram')
                    ->label('В Telegram')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Опубликовать в Telegram')
                    ->modalDescription(fn (News $record): string => $record->published_to_tg
                        ? 'Эта новость уже была отправлена в Telegram. Отправить ещё раз?'
                        : 'Отправить новость в Telegram-канал?')
                    ->modalSubmitActionLabel('Отправить')
                    ->action(function (News $record): void {
                        if (! $record->isPublished()) {
                            Notification::make()
                                ->warning()
                                ->title('Новость ещё не опубликована')
                                ->body('Сначала задайте дату публикации не в будущем — иначе ссылка на новость не откроется.')
                                ->send();

                            return;
                        }

                        if (! app(TelegramService::class)->isConfigured()) {
                            Notification::make()
                                ->warning()
                                ->title('Telegram не настроен')
                                ->body('Не заданы TELEGRAM_BOT_TOKEN и/или TELEGRAM_CHAT_ID.')
                                ->send();

                            return;
                        }

                        PublishNewsToTelegram::dispatch($record->id, force: true);

                        Notification::make()
                            ->success()
                            ->title('Поставлено в очередь')
                            ->body('Новость будет отправлена в Telegram в ближайшее время.')
                            ->send();
                    }),
                Action::make('publishToVk')
                    ->label('В VK')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Опубликовать в VK')
                    ->modalDescription(fn (News $record): string => $record->published_to_vk
                        ? 'Эта новость уже была отправлена в VK. Отправить ещё раз?'
                        : 'Отправить новость в сообщество VK?')
                    ->modalSubmitActionLabel('Отправить')
                    ->action(function (News $record): void {
                        if (! $record->isPublished()) {
                            Notification::make()
                                ->warning()
                                ->title('Новость ещё не опубликована')
                                ->body('Сначала задайте дату публикации не в будущем — иначе ссылка на новость не откроется.')
                                ->send();

                            return;
                        }

                        if (! app(VkService::class)->isConfigured()) {
                            Notification::make()
                                ->warning()
                                ->title('VK не настроен')
                                ->body('Не заданы данные VK ID (VK_CLIENT_ID / VK_CLIENT_SECRET / VK_REFRESH_TOKEN) и/или VK_GROUP_ID.')
                                ->send();

                            return;
                        }

                        PublishNewsToVk::dispatch($record->id, force: true);

                        Notification::make()
                            ->success()
                            ->title('Поставлено в очередь')
                            ->body('Новость будет отправлена в VK в ближайшее время.')
                            ->send();
                    }),
                EditAction::make()->modalHeading('Редактировать новость'),
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
            'index' => ManageNews::route('/'),
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
